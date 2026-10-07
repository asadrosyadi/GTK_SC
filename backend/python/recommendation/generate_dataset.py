import argparse
import os

import numpy as np
import pandas as pd

from .features import FEATURE_COLUMNS, add_group_features, add_phase_features
from .knowledge_base import ACTION_IDS, CNN_SLUGS, NEUTRAL, crop_phase, label_actions, vpd_kpa

HERE = os.path.dirname(os.path.abspath(__file__))
DEFAULT_OUT = os.path.join(HERE, 'data', 'recommendation_dataset.csv')

SENSOR_SPEC = {
    'temperature':     (18, 40, 29, 3.5, 0.5),
    'humidity':        (40, 100, 82, 9, 2.0),
    'windspeed':       (0, 15, 2, 2.5, 0.3),
    'rainfall':        (0, 150, 0, 25, 1.0),
    'light_intensity': (0, 2000, 600, 300, 20),
    'ph':              (3.5, 9.0, 6.3, 0.8, 0.1),
    'soil_moisture':   (10, 60, 46, 7, 1.5),
    'ec':              (100, 6000, 900, 700, 50),
    'tds':             (50, 4000, 550, 450, 30),
    'soil_temp':       (18, 40, 29, 3, 0.5),
    'nitrogen':        (0, 100, 35, 14, 2.0),
    'phosphorus':      (0, 40, 12, 6, 1.0),
    'potassium':       (20, 300, 150, 60, 5.0),
}
SOIL_KEYS = ['ph', 'soil_moisture', 'ec', 'tds', 'soil_temp', 'nitrogen', 'phosphorus', 'potassium']


def sample_true(rng):
    v = {}
    for key, (lo, hi, center, spread, _) in SENSOR_SPEC.items():
        if rng.random() < 0.6:
            x = rng.normal(center, spread)
        else:
            x = rng.uniform(lo, hi)
        v[key] = float(np.clip(x, lo, hi))
    if rng.random() < 0.65:
        v['rainfall'] = 0.0
    v['vpd'] = vpd_kpa(v['temperature'], v['humidity'])

    for slug in CNN_SLUGS:
        v[f'pest_{slug}'] = 0.0
    r = rng.random()
    if r < 0.10:
        v['pest_available'] = 0.0
    else:
        v['pest_available'] = 1.0
        if r < 0.45:
            v['pest_normal'] = float(rng.uniform(0.5, 1.0))
        else:
            cls = int(rng.choice([i for i in range(len(CNN_SLUGS)) if CNN_SLUGS[i] != 'normal']))
            v[f'pest_{CNN_SLUGS[cls]}'] = float(rng.uniform(0.25, 1.0))

    if rng.random() < 0.2:
        add_phase_features(v, None)
    else:
        umur = int(rng.integers(105, 126))
        add_phase_features(v, crop_phase(int(rng.integers(0, umur - 21 + 5)), umur, 21))

    if rng.random() < 0.15:
        v['health_available'] = 0.0
        v['health_healthy'] = v['health_moderate'] = v['health_high'] = 0.0
    else:
        alpha = np.array([3.0, 2.0, 1.0])
        if v['soil_moisture'] < 35 or v['vpd'] >= 1.5:
            alpha = np.array([1.0, 2.0, 5.0])
        p = rng.dirichlet(alpha)
        v['health_available'] = 1.0
        v['health_healthy'], v['health_moderate'], v['health_high'] = map(float, p)
    return v


def observe(true, rng):
    obs = dict(true)
    for key, (lo, hi, _, _, noise) in SENSOR_SPEC.items():
        obs[key] = float(np.clip(true[key] + rng.normal(0, noise), lo, hi))
    if true['rainfall'] == 0.0:
        obs['rainfall'] = 0.0
    obs['vpd'] = vpd_kpa(obs['temperature'], obs['humidity'])
    for slug in CNN_SLUGS:
        c = true[f'pest_{slug}']
        if c > 0:
            obs[f'pest_{slug}'] = float(np.clip(c + rng.normal(0, 0.03), 0.01, 1.0))
    return add_group_features(obs)


def generate(n, seed=42):
    rng = np.random.default_rng(seed)
    rows = []
    for _ in range(n):
        true = sample_true(rng)
        for key in SOIL_KEYS:
            if rng.random() < 0.03:
                true[key] = NEUTRAL[key]
        true['vpd'] = vpd_kpa(true['temperature'], true['humidity'])
        labels = label_actions(true)
        obs = observe(true, rng)
        row = {c: obs[c] for c in FEATURE_COLUMNS}
        row.update({f'y_{a}': int(a in labels) for a in ACTION_IDS})
        rows.append(row)
    return pd.DataFrame(rows)


def main():
    parser = argparse.ArgumentParser(description=__doc__, formatter_class=argparse.RawDescriptionHelpFormatter)
    parser.add_argument('--n', type=int, default=40000)
    parser.add_argument('--seed', type=int, default=42)
    parser.add_argument('--out', default=DEFAULT_OUT)
    args = parser.parse_args()

    df = generate(args.n, args.seed)
    os.makedirs(os.path.dirname(args.out), exist_ok=True)
    df.to_csv(args.out, index=False)
    print(f'[dataset] {len(df)} sampel -> {args.out}')
    print(df[[f'y_{a}' for a in ACTION_IDS]].mean().round(3).to_string())


if __name__ == '__main__':
    main()
