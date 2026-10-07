import math
from datetime import date, datetime

from .knowledge_base import (CNN_CLASSES, CNN_SLUGS, N_SENSITIVE_GROUPS, NEUTRAL, PEST_GROUPS, PHASES,
                             crop_phase, vpd_kpa)

WEATHER_FEATURES = ['temperature', 'humidity', 'vpd', 'windspeed', 'rainfall', 'light_intensity']
SOIL_FEATURES = ['ph', 'soil_moisture', 'ec', 'tds', 'soil_temp', 'nitrogen', 'phosphorus', 'potassium']
PEST_FEATURES = [f'pest_{slug}' for slug in CNN_SLUGS]
GROUP_FEATURES = [f'grp_{g}' for g in PEST_GROUPS] + ['grp_n_sensitive']
HEALTH_FEATURES = ['health_healthy', 'health_moderate', 'health_high']
PHASE_FEATURES = ['hst', 'fase_diketahui'] + [f'fase_{p}' for p in PHASES] + ['menjelang_panen']
FLAG_FEATURES = ['pest_available', 'health_available']

FEATURE_COLUMNS = (WEATHER_FEATURES + SOIL_FEATURES + PEST_FEATURES + GROUP_FEATURES
                   + HEALTH_FEATURES + PHASE_FEATURES + FLAG_FEATURES)

SOIL_ALIASES = {
    'Nitrogen_Level': 'nitrogen', 'Phosphorus_Level': 'phosphorus', 'Potassium_Level': 'potassium',
    'fosfor': 'phosphorus',
}


def _num(value):
    try:
        if value is None or value == '':
            return None
        f = float(value)
        return None if math.isnan(f) or math.isinf(f) else f
    except (TypeError, ValueError):
        return None


def _pick(source, key):
    if not source:
        return None
    if key in source:
        return _num(source[key])
    for alias, target in SOIL_ALIASES.items():
        if target == key and alias in source:
            return _num(source[alias])
    return None


def add_group_features(values):
    for group, idx in PEST_GROUPS.items():
        values[f'grp_{group}'] = max(values.get(f'pest_{CNN_SLUGS[i]}', 0.0) for i in idx)
    values['grp_n_sensitive'] = max(values[f'grp_{g}'] for g in N_SENSITIVE_GROUPS)
    return values


def add_phase_features(values, phase):
    values['hst'] = float(phase['hst']) if phase else -1.0
    values['fase_diketahui'] = 1.0 if phase else 0.0
    for p in PHASES:
        values[f'fase_{p}'] = 1.0 if phase and phase['fase'] == p else 0.0
    values['menjelang_panen'] = 1.0 if phase and phase['menjelang_panen'] else 0.0
    return values


def planting_phase(planting, today=None):
    if not planting:
        return None
    hst = _num(planting.get('hst'))
    if hst is None and planting.get('tanggal_tanam'):
        try:
            tanam = datetime.strptime(str(planting['tanggal_tanam'])[:10], '%Y-%m-%d').date()
            hst = ((today or date.today()) - tanam).days
        except ValueError:
            hst = None
    if hst is None or hst < 0:
        return None
    return crop_phase(int(hst), int(_num(planting.get('umur_varietas')) or 115),
                      int(_num(planting.get('umur_bibit')) or 21))


def build_features(weather=None, soil=None, camera=None, health=None, planting=None):

    values, missing = {}, []

    for key in ['temperature', 'humidity', 'windspeed', 'rainfall', 'light_intensity']:
        v = _pick(weather, key)
        if v is None:
            missing.append(key)
            v = NEUTRAL[key]
        values[key] = v
    values['humidity'] = min(max(values['humidity'], 0.0), 100.0)
    values['vpd'] = vpd_kpa(values['temperature'], values['humidity'])

    for key in SOIL_FEATURES:
        v = _pick(soil, key)
        if v is None:
            missing.append(key)
            v = NEUTRAL[key]
        values[key] = v

    for col in PEST_FEATURES:
        values[col] = 0.0
    values['pest_available'] = 0.0
    if camera and camera.get('penyakit') in CNN_CLASSES:
        conf = _num(camera.get('probabilitas')) or 0.0
        if conf > 1.0:
            conf /= 100.0
        values[f"pest_{CNN_SLUGS[CNN_CLASSES.index(camera['penyakit'])]}"] = min(max(conf, 0.0), 1.0)
        values['pest_available'] = 1.0
    else:
        missing.append('camera')

    values['health_healthy'] = values['health_moderate'] = values['health_high'] = 0.0
    values['health_available'] = 0.0
    if health:
        values['health_healthy'] = _num(health.get('Healthy')) or 0.0
        values['health_moderate'] = _num(health.get('Moderate Stress')) or 0.0
        values['health_high'] = _num(health.get('High Stress')) or 0.0
        values['health_available'] = 1.0
    else:
        missing.append('lstm')

    phase = planting_phase(planting)
    if phase is None:
        missing.append('tanggal_tanam')
    add_phase_features(values, phase)

    return add_group_features(values), missing


def to_row(values):
    return [float(values[c]) for c in FEATURE_COLUMNS]
