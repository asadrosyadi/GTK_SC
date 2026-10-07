import math
import warnings
from datetime import datetime

import numpy as np
import pandas as pd

try:
    from statsmodels.tsa.holtwinters import ExponentialSmoothing, SimpleExpSmoothing
except Exception:
    ExponentialSmoothing = SimpleExpSmoothing = None

try:
    from recommendation.knowledge_base import T as KB
except Exception:
    KB = None

STEP_HOURS = 4
SEASON = 24 // STEP_HOURS
BACKTEST_HORIZON = SEASON
MAX_ORIGINS = 4
MAX_FORECAST_DAYS = 30
TDS_PER_EC_FAO29 = 0.64

SENSORS = [
    ('temperature', 'Suhu udara', '°C', 'continuous', None, None),
    ('humidity', 'Kelembapan udara', '%', 'continuous', 0, 100),
    ('windspeed', 'Kecepatan angin', 'm/s', 'continuous', 0, None),
    ('rainfall', 'Curah hujan', 'mm/hari', 'intermittent', 0, None),
    ('light_intensity', 'Intensitas cahaya', 'lux', 'continuous', 0, None),
    ('pressure', 'Tekanan udara', 'Pa', 'continuous', 0, None),
    ('ph', 'pH tanah', '', 'continuous', 0, 14),
    ('soil_moisture', 'Kadar air tanah', '%', 'continuous', 0, 100),
    ('ec', 'EC', 'µS/cm', 'continuous', 0, None),
    ('soil_temp', 'Suhu tanah', '°C', 'continuous', None, None),
    ('Nitrogen_Level', 'Nitrogen (N)', 'mg/kg', 'continuous', 0, None),
    ('Phosphorus_Level', 'Fosfor (P)', 'mg/kg', 'continuous', 0, None),
    ('Potassium_Level', 'Kalium (K)', 'mg/kg', 'continuous', 0, None),
]
DERIVED = [
    ('vpd', 'VPD', 'kPa', 'FAO-56: VPD = e_s(T)·(1 − RH/100), e_s = 0,6108·exp(17,27T/(T+237,3))'),
    ('tds', 'TDS', 'ppm', 'TDS = k × EC'),
]
VARIABLE_ORDER = ['temperature', 'humidity', 'vpd', 'windspeed', 'rainfall', 'light_intensity', 'pressure',
                  'ph', 'soil_moisture', 'ec', 'tds', 'soil_temp', 'Nitrogen_Level', 'Phosphorus_Level',
                  'Potassium_Level']

METHOD_LABELS = {
    'naive': 'Persistensi (nilai terakhir)',
    'seasonal_naive': 'Musiman naif (nilai 24 jam sebelumnya)',
    'ses': 'Exponential smoothing sederhana',
    'holt_winters': 'Holt-Winters aditif, tren teredam',
    'climatology': 'Klimatologi riwayat per jam',
}


def _clean(value):
    try:
        f = float(value)
    except (TypeError, ValueError):
        return None
    return None if math.isnan(f) or math.isinf(f) else round(f, 3)


def vpd_fao56(temperature, humidity):
    if temperature is None or humidity is None:
        return None
    es = 0.6108 * math.exp((17.27 * temperature) / (temperature + 237.3))
    return max(0.0, es * (1 - humidity / 100.0))


def resample_history(df):
    df = df.copy()
    df['created_at'] = pd.to_datetime(df['created_at'], errors='coerce')
    df = df.dropna(subset=['created_at']).sort_values('created_at').set_index('created_at')
    out = {}
    for key, _, _, kind, _, _ in SENSORS:
        if key not in df.columns:
            continue
        series = pd.to_numeric(df[key], errors='coerce')
        if kind == 'intermittent':
            out[key] = series.resample(f'{STEP_HOURS}h').sum(min_count=1)
        else:
            out[key] = series.resample(f'{STEP_HOURS}h').mean()
    if 'tds' in df.columns:
        out['tds'] = pd.to_numeric(df['tds'], errors='coerce').resample(f'{STEP_HOURS}h').mean()
    frame = pd.DataFrame(out)
    for key, _, _, kind, _, _ in SENSORS:
        if key in frame.columns and kind == 'continuous':
            frame[key] = frame[key].interpolate(method='time', limit=2, limit_area='inside')
    return frame


def _fc_naive(y, h):
    return np.repeat(y[-1], h)


def _fc_seasonal_naive(y, h):
    last_season = y[-SEASON:]
    return np.array([last_season[i % SEASON] for i in range(h)])


def _fc_climatology(y, h, slots, next_slot):
    means = pd.Series(y).groupby(slots).mean()
    return np.array([means.get((next_slot + i) % SEASON, np.nanmean(y)) for i in range(h)])


def _fc_ses(y, h):
    with warnings.catch_warnings():
        warnings.simplefilter('ignore')
        return SimpleExpSmoothing(y, initialization_method='estimated').fit().forecast(h)


def _fc_holt_winters(y, h):
    with warnings.catch_warnings():
        warnings.simplefilter('ignore')
        model = ExponentialSmoothing(y, trend='add', damped_trend=True, seasonal='add',
                                     seasonal_periods=SEASON, initialization_method='estimated')
        return model.fit().forecast(h)


def _candidates(kind, n):
    names = ['naive']
    if n >= SEASON + 1:
        names.append('seasonal_naive')
    if kind == 'intermittent':
        if n >= SEASON:
            names.append('climatology')
        return names
    if SimpleExpSmoothing is not None and n >= 8:
        names.append('ses')
    if ExponentialSmoothing is not None and n >= 3 * SEASON:
        names.append('holt_winters')
    return names


def _run(method, y, h, slots=None, next_slot=None):
    if method == 'naive':
        return _fc_naive(y, h)
    if method == 'seasonal_naive':
        return _fc_seasonal_naive(y, h)
    if method == 'climatology':
        return _fc_climatology(y, h, slots, next_slot)
    if method == 'ses':
        return _fc_ses(y, h)
    if method == 'holt_winters':
        return _fc_holt_winters(y, h)
    raise ValueError(method)


def _mase_scale(y):
    lag = SEASON if len(y) > SEASON + 1 else 1
    diffs = np.abs(y[lag:] - y[:-lag])
    scale = np.nanmean(diffs) if len(diffs) else np.nan
    return scale if scale and not math.isnan(scale) and scale > 0 else None


def select_and_forecast(series, kind, h, lower=None, upper=None):
    clean = series.dropna()
    y = clean.to_numpy(dtype=float)
    n = len(y)
    if n == 0:
        return None, {'method': None, 'note': 'Belum ada data sensor'}
    slots = (clean.index.hour // STEP_HOURS).to_numpy()
    next_slot = int(((clean.index[-1].hour // STEP_HOURS) + 1) % SEASON)

    min_train = 3 * SEASON
    origins = [n - BACKTEST_HORIZON - k for k in range(MAX_ORIGINS) if n - BACKTEST_HORIZON - k >= min_train]
    candidates = _candidates(kind, n)
    scores = {}
    if origins:
        for method in candidates:
            errors = []
            for o in origins:
                train, test = y[:o], y[o:o + BACKTEST_HORIZON]
                try:
                    pred = _run(method, train, len(test), slots[:o], int((slots[o - 1] + 1) % SEASON))
                except Exception:
                    errors = None
                    break
                errors.extend(np.abs(np.asarray(pred, dtype=float) - test))
            if errors:
                scores[method] = float(np.nanmean(errors))

    if scores:
        method = min(scores, key=scores.get)
        mae = scores[method]
        scale = _mase_scale(y)
        info = {'method': method, 'mae': round(mae, 4), 'mase': round(mae / scale, 3) if scale else None,
                'backtest_origins': len(origins), 'candidates': {m: round(v, 4) for m, v in scores.items()}}
    else:
        method = 'naive'
        info = {'method': method, 'mae': None, 'mase': None, 'backtest_origins': 0,
                'note': f'Riwayat {n} blok 4 jam belum cukup untuk validasi (perlu >= {min_train + BACKTEST_HORIZON}); '
                        'memakai persistensi'}
    try:
        forecast = np.asarray(_run(method, y, h, slots, next_slot), dtype=float)
    except Exception as e:
        forecast = _fc_naive(y, h)
        info.update({'method': 'naive', 'note': f'Model {method} gagal ({e}); memakai persistensi'})
    if lower is not None or upper is not None:
        forecast = np.clip(forecast, lower if lower is not None else -np.inf, upper if upper is not None else np.inf)
    info['n_blocks'] = n
    info['method_label'] = METHOD_LABELS.get(info['method'], info['method'])
    return forecast, info


def step_status(row, rain_24h):
    if KB is None:
        return []
    s = []
    t, rh = row.get('temperature'), row.get('humidity')
    if t is not None and t >= KB['temp_heat']:
        s.append(('panas', 'Udara terlalu panas'))
    if t is not None and rh is not None and rh >= KB['blast_rh'] and KB['blast_t_min'] <= t <= KB['blast_t_max']:
        s.append(('blas', 'Rawan penyakit blas'))
    if (t is not None and rh is not None and rain_24h is not None and rain_24h >= KB['blb_rain_24h']
            and rh > KB['blb_rh'] and KB['blb_t_min'] <= t <= KB['blb_t_max']):
        s.append(('hawar_bakteri', 'Rawan penyakit kresek'))
    vwc = row.get('soil_moisture')
    if vwc is not None and vwc < KB['soil_moisture_irrigate']:
        s.append(('irigasi', 'Tanah kering, perlu diairi'))
    ec, tds = row.get('ec'), row.get('tds')
    if (ec is not None and ec >= KB['ec_saline']) or (tds is not None and tds >= KB['tds_saline']):
        s.append(('salin', 'Tanah/air terlalu asin'))
    ph = row.get('ph')
    if ph is not None and ph <= KB['ph_acid']:
        s.append(('ph_masam', 'Tanah terlalu masam'))
    elif ph is not None and ph > KB['ph_alkaline']:
        s.append(('ph_basa', 'Tanah terlalu basa'))
    wind = row.get('windspeed')
    if wind is not None and wind >= KB['wind_lodging']:
        s.append(('angin', 'Angin kencang, padi rawan rebah'))
    return [{'code': c, 'label': lbl} for c, lbl in s]


def tds_per_ec(frame):
    if 'tds' in frame and 'ec' in frame:
        pasangan = frame[['tds', 'ec']].dropna()
        pasangan = pasangan[(pasangan['ec'] > 0) & (pasangan['tds'] > 0)]
        if len(pasangan) >= 3:
            rasio = float((pasangan['tds'] / pasangan['ec']).median())
            if 0.3 <= rasio <= 1.0:
                return rasio, f'median rasio TDS/EC terukur, {len(pasangan)} blok'
    return TDS_PER_EC_FAO29, 'FAO-29, belum ada pasangan TDS-EC terukur'


def forecast_sensors(history_df, days=3, titik=1):
    days = int(max(1, min(MAX_FORECAST_DAYS, days)))
    h = days * SEASON
    frame = resample_history(history_df)
    if frame.empty:
        return None
    last_block = frame.index[-1]
    future_index = [last_block + pd.Timedelta(hours=STEP_HOURS * (i + 1)) for i in range(h)]

    values, variables = {}, {}
    for key, label, unit, kind, lo, hi in SENSORS:
        if key not in frame.columns:
            variables[key] = {'key': key, 'label': label, 'unit': unit, 'method': None, 'note': 'Sensor tidak ada'}
            continue
        fc, info = select_and_forecast(frame[key], kind, h, lo, hi)
        values[key] = fc
        variables[key] = {'key': key, 'label': label, 'unit': unit, **info}

    t_fc, rh_fc = values.get('temperature'), values.get('humidity')
    values['vpd'] = (np.array([vpd_fao56(a, b) for a, b in zip(t_fc, rh_fc)], dtype=float)
                     if t_fc is not None and rh_fc is not None else None)
    k_tds, k_sumber = tds_per_ec(frame)
    values['tds'] = values['ec'] * k_tds if values.get('ec') is not None else None
    for key, label, unit, formula in DERIVED:
        if key == 'tds':
            formula = f'TDS = {k_tds:.3f} × EC ({k_sumber})'
        variables[key] = {'key': key, 'label': label, 'unit': unit, 'method': 'derived',
                          'method_label': 'Turunan', 'formula': formula,
                          'note': None if values.get(key) is not None else 'Masukan belum tersedia'}

    rows = []
    rain = values.get('rainfall')
    if rain is not None and 'rainfall' in frame:
        past = frame['rainfall'].to_numpy(dtype=float)[-(SEASON - 1):]
        rain_window = np.concatenate([past, np.asarray(rain, dtype=float)])
        offset = len(past)
    else:
        rain_window, offset = rain, 0
    for i, ts in enumerate(future_index):
        row = {'date': ts.strftime('%Y-%m-%d %H:%M')}
        for key in VARIABLE_ORDER:
            arr = values.get(key)
            row[key] = _clean(arr[i]) if arr is not None else None
        j = i + offset
        rain_24h = float(np.nansum(rain_window[max(0, j - SEASON + 1):j + 1])) if rain is not None else None
        row['rainfall_24h'] = _clean(rain_24h)
        row['status'] = step_status(row, rain_24h)
        if rain is not None:
            row['rainfall'] = row['rainfall_24h']
        rows.append(row)

    hist = frame.tail(2 * SEASON).copy()
    if 'rainfall' in frame:
        hist['rainfall'] = frame['rainfall'].rolling(SEASON, min_periods=1).sum().tail(2 * SEASON)
    if 'temperature' in hist and 'humidity' in hist:
        hist['vpd'] = [vpd_fao56(a, b) if not (pd.isna(a) or pd.isna(b)) else None
                       for a, b in zip(hist['temperature'], hist['humidity'])]
    if 'ec' in hist:
        tds_turunan = hist['ec'] * k_tds
        hist['tds'] = hist['tds'].fillna(tds_turunan) if 'tds' in hist else tds_turunan
    history = []
    for ts, r in hist.iterrows():
        item = {'date': ts.strftime('%Y-%m-%d %H:%M')}
        for key in VARIABLE_ORDER:
            item[key] = _clean(r[key]) if key in r else None
        history.append(item)

    iot_id = None
    if 'iot_id' in history_df.columns and not history_df['iot_id'].dropna().empty:
        iot_id = str(history_df['iot_id'].dropna().iloc[-1])

    return {
        'iot_id': iot_id,
        'titik': titik,
        'generated_at': datetime.now().strftime('%Y-%m-%d %H:%M:%S'),
        'resolution_hours': STEP_HOURS,
        'days': days,
        'last_observation': last_block.strftime('%Y-%m-%d %H:%M'),
        'variables': [variables[k] for k in VARIABLE_ORDER if k in variables],
        'history': history,
        'forecast': rows,
    }
