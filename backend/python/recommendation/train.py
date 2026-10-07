import argparse
import json
import os
from datetime import datetime

import joblib
import numpy as np
import pandas as pd
import sklearn
from sklearn.ensemble import RandomForestClassifier
from sklearn.metrics import accuracy_score, f1_score, hamming_loss, precision_score, recall_score
from sklearn.model_selection import train_test_split
from sklearn.multioutput import MultiOutputClassifier

from . import generate_dataset
from .features import FEATURE_COLUMNS
from .knowledge_base import ACTION_IDS, ACTIONS, label_actions

HERE = os.path.dirname(os.path.abspath(__file__))
MODEL_DIR = os.path.join(HERE, 'models')
MODEL_PATH = os.path.join(MODEL_DIR, 'recommendation_rf.joblib')
META_PATH = os.path.join(MODEL_DIR, 'recommendation_rf_meta.json')
REPORT_PATH = os.path.join(MODEL_DIR, 'recommendation_rf_report.md')


def evaluate(y_true, y_pred):
    return {
        'subset_accuracy': float(accuracy_score(y_true, y_pred)),
        'hamming_loss': float(hamming_loss(y_true, y_pred)),
        'f1_micro': float(f1_score(y_true, y_pred, average='micro', zero_division=0)),
        'f1_macro': float(f1_score(y_true, y_pred, average='macro', zero_division=0)),
        'precision_micro': float(precision_score(y_true, y_pred, average='micro', zero_division=0)),
        'recall_micro': float(recall_score(y_true, y_pred, average='micro', zero_division=0)),
    }


def main():
    parser = argparse.ArgumentParser(description=__doc__, formatter_class=argparse.RawDescriptionHelpFormatter)
    parser.add_argument('--data', default=generate_dataset.DEFAULT_OUT)
    parser.add_argument('--regen', action='store_true')
    parser.add_argument('--n', type=int, default=40000)
    parser.add_argument('--trees', type=int, default=100)
    parser.add_argument('--seed', type=int, default=42)
    args = parser.parse_args()

    if args.regen or not os.path.exists(args.data):
        df = generate_dataset.generate(args.n, args.seed)
        os.makedirs(os.path.dirname(args.data), exist_ok=True)
        df.to_csv(args.data, index=False)
        print(f'[train] dataset baru: {len(df)} sampel -> {args.data}')
    else:
        df = pd.read_csv(args.data)
        print(f'[train] dataset: {len(df)} sampel dari {args.data}')

    label_cols = [f'y_{a}' for a in ACTION_IDS]
    X = df[FEATURE_COLUMNS].to_numpy(dtype=np.float32)
    Y = df[label_cols].to_numpy(dtype=np.int8)
    X_train, X_test, Y_train, Y_test = train_test_split(X, Y, test_size=0.2, random_state=args.seed)

    model = MultiOutputClassifier(RandomForestClassifier(
        n_estimators=args.trees, min_samples_leaf=4, max_features=0.5, max_depth=18,
        n_jobs=-1, random_state=args.seed,
    ))
    model.fit(X_train, Y_train)
    Y_pred = model.predict(X_test)

    test_df = pd.DataFrame(X_test, columns=FEATURE_COLUMNS)
    Y_rule = np.array([[int(a in label_actions(row)) for a in ACTION_IDS]
                       for row in test_df.to_dict('records')], dtype=np.int8)

    metrics_rf = evaluate(Y_test, Y_pred)
    metrics_rule = evaluate(Y_test, Y_rule)
    per_label = {
        a: {
            'f1': float(f1_score(Y_test[:, i], Y_pred[:, i], zero_division=0)),
            'support': int(Y_test[:, i].sum()),
        }
        for i, a in enumerate(ACTION_IDS)
    }
    mean_importance = np.mean([est.feature_importances_ for est in model.estimators_], axis=0)
    importances = sorted(zip(FEATURE_COLUMNS, mean_importance), key=lambda x: -x[1])

    meta = {
        'name': 'Random Forest per-aksi (multi-label)',
        'version': datetime.now().strftime('%Y%m%d%H%M'),
        'trained_at': datetime.now().isoformat(timespec='seconds'),
        'sklearn_version': sklearn.__version__,
        'n_samples': int(len(df)), 'n_train': int(len(X_train)), 'n_test': int(len(X_test)),
        'n_estimators': args.trees,
        'features': FEATURE_COLUMNS,
        'labels': ACTION_IDS,
        'metrics': metrics_rf,
        'baseline_rules_on_noisy_sensors': metrics_rule,
        'per_label': per_label,
        'top_features': [[f, float(w)] for f, w in importances[:15]],
    }

    os.makedirs(MODEL_DIR, exist_ok=True)
    joblib.dump({'model': model, 'meta': meta}, MODEL_PATH, compress=3)
    with open(META_PATH, 'w', encoding='utf-8') as f:
        json.dump(meta, f, indent=2, ensure_ascii=False)

    lines = [
        '# Evaluasi Model Rekomendasi Random Forest', '',
        f"Dilatih: {meta['trained_at']} · sampel latih {meta['n_train']}, uji {meta['n_test']} · "
        f"{args.trees} pohon · scikit-learn {sklearn.__version__}", '',
        '| Metrik | Random Forest | Aturan pada sensor berderau |', '|---|---|---|',
    ]
    for k in metrics_rf:
        lines.append(f'| {k} | {metrics_rf[k]:.4f} | {metrics_rule[k]:.4f} |')
    lines += ['', '| Aksi | F1 | Support |', '|---|---|---|']
    for a in ACTION_IDS:
        lines.append(f"| {a} — {ACTIONS[a]['title']} | {per_label[a]['f1']:.4f} | {per_label[a]['support']} |")
    lines += ['', '## Fitur paling berpengaruh', '']
    lines += [f'{i + 1}. {f} ({w:.4f})' for i, (f, w) in enumerate(importances[:15])]
    with open(REPORT_PATH, 'w', encoding='utf-8') as f:
        f.write('\n'.join(lines) + '\n')

    print(f"[train] RF   : F1-micro {metrics_rf['f1_micro']:.4f} | F1-macro {metrics_rf['f1_macro']:.4f} | "
          f"subset-acc {metrics_rf['subset_accuracy']:.4f} | hamming {metrics_rf['hamming_loss']:.4f}")
    print(f"[train] Rules: F1-micro {metrics_rule['f1_micro']:.4f} | F1-macro {metrics_rule['f1_macro']:.4f} | "
          f"subset-acc {metrics_rule['subset_accuracy']:.4f} | hamming {metrics_rule['hamming_loss']:.4f}")
    print(f'[train] model -> {MODEL_PATH}')


if __name__ == '__main__':
    main()
