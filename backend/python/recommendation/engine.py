import os
from datetime import datetime

import numpy as np

from .features import FEATURE_COLUMNS, build_features, to_row
from .knowledge_base import (ACTION_IDS, ACTIONS, ACTUATION_PRIORITY, ACT_MONITOR, explain,
                             label_actions, phase_of, sources_for)

HERE = os.path.dirname(os.path.abspath(__file__))
MODEL_PATH = os.path.join(HERE, 'models', 'recommendation_rf.joblib')
TYPE_RANK = {'danger': 0, 'warning': 1, 'info': 2, 'success': 3}


class RecommendationEngine:
    def __init__(self, model_path=MODEL_PATH, threshold=0.5):
        self.threshold = threshold
        self.model = None
        self.meta = {}
        try:
            import joblib
            bundle = joblib.load(model_path)
            self.model, self.meta = bundle['model'], bundle['meta']
            if self.meta.get('features') != FEATURE_COLUMNS or self.meta.get('labels') != ACTION_IDS:
                print('[Recommendation] fitur/label model tidak cocok dengan kode, latih ulang model')
                self.model = None
            else:
                print(f"[Recommendation] model RF dimuat (versi {self.meta.get('version')}, "
                      f"F1-micro {self.meta.get('metrics', {}).get('f1_micro', 0):.3f})")
        except Exception as e:
            print(f'[Recommendation] model tidak dimuat ({e}); memakai basis pengetahuan')

    @property
    def source(self):
        return 'random_forest' if self.model is not None else 'rules'

    def _predict_proba(self, values):
        if self.model is None:
            labels = label_actions(values)
            return {a: (1.0 if a in labels else 0.0) for a in ACTION_IDS}
        proba = self.model.predict_proba(np.array([to_row(values)], dtype=np.float32))
        out = {}
        estimators = getattr(self.model, 'estimators_', None)
        for i, a in enumerate(ACTION_IDS):
            classes = list(estimators[i].classes_) if estimators is not None else list(self.model.classes_[i])
            out[a] = float(proba[i][0][classes.index(1)]) if 1 in classes else 0.0
        return out

    def recommend_petak(self, weather, soil, camera, health, planting=None):
        values, missing = build_features(weather, soil, camera, health, planting)
        proba = self._predict_proba(values)

        active = [a for a in ACTION_IDS if a != 'pantau' and proba[a] >= self.threshold]
        if not active:
            active = ['pantau']

        recs = []
        for a in active:
            info = ACTIONS[a]
            reasons = explain(a, values) or ['Pola gabungan data sensor (model Random Forest)']
            recs.append({
                'action': a,
                'title': info['title'],
                'message': info['message'],
                'type': info['type'],
                'icon': info['icon'],
                'probability': round(proba.get(a, 1.0), 4),
                'reasons': reasons,
                'sources': sources_for(a),
            })
        recs.sort(key=lambda r: (TYPE_RANK.get(r['type'], 9), -r['probability']))
        for i, r in enumerate(recs, start=1):
            r['priority'] = i

        actuation_id = next((a for a in ACTUATION_PRIORITY if a in active), None)
        if actuation_id:
            info = ACTIONS[actuation_id]
            actuation = {'action': info['actuation'], 'actuators': info['actuators'],
                         'type': info['type'], 'icon': info['icon'], 'title': info['title'],
                         'message': '; '.join(explain(actuation_id, values)) or info['message'],
                         'source_action': actuation_id, 'sources': sources_for(actuation_id)}
        else:
            info = ACTIONS['pantau']
            actuation = {'action': ACT_MONITOR, 'actuators': [], 'type': 'success', 'icon': info['icon'],
                         'title': info['title'], 'message': info['message'], 'source_action': 'pantau'}

        return {
            'inputs': {k: round(v, 3) for k, v in values.items()},
            'missing': missing,
            'fase': phase_of(values),
            'hst': None if values['hst'] < 0 else int(values['hst']),
            'recommendations': recs,
            'actuation': actuation,
        }

    def recommend(self, snapshot, health_provider=None):
        weather = snapshot.get('weather') or {}
        result = {
            'iot_id': snapshot.get('iot_id'),
            'generated_at': datetime.now().isoformat(timespec='seconds'),
            'model': {
                'source': self.source,
                'name': self.meta.get('name', 'Basis pengetahuan agronomi'),
                'version': self.meta.get('version'),
                'f1_micro': self.meta.get('metrics', {}).get('f1_micro'),
                'threshold': self.threshold,
            },
            'petak': {},
            'summary': [],
        }

        summary = {}
        for titik, node in sorted((snapshot.get('titik') or {}).items(), key=lambda kv: int(kv[0])):
            health = node.get('health')
            if health is None and health_provider is not None:
                try:
                    health = health_provider(int(titik))
                except Exception as e:
                    print(f'[Recommendation] LSTM titik {titik} gagal: {e}')
                    health = None

            out = self.recommend_petak(weather, node.get('soil'), node.get('camera'), health, node.get('tanam'))
            out['label'] = node.get('label') or f'Petak {titik}'
            out['health'] = health
            out['camera'] = node.get('camera')
            result['petak'][str(titik)] = out

            for r in out['recommendations']:
                item = summary.setdefault(r['action'], {**r, 'petak': [], 'reasons_by_petak': {}})
                item['petak'].append(out['label'])
                item['reasons_by_petak'][out['label']] = r['reasons']
                item['probability'] = max(item['probability'], r['probability'])

        items = list(summary.values())
        if any(i['action'] != 'pantau' for i in items):
            items = [i for i in items if i['action'] != 'pantau']
        items.sort(key=lambda r: (TYPE_RANK.get(r['type'], 9), -len(r['petak']), -r['probability']))
        for i, item in enumerate(items, start=1):
            item['priority'] = i
            item.pop('reasons', None)
        result['summary'] = items
        return result
