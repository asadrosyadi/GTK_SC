import pandas as pd
import numpy as np
import math

COLUMN_MAP = {
    'soil_moisture': 'Soil_Moisture',
    'temperature': 'Ambient_Temperature',
    'soil_temp': 'Soil_Temperature',
    'humidity': 'Humidity',
    'light_intensity': 'Light_Intensity',
    'ph': 'Soil_pH',
}

DEFAULT_FEATURES = {
    'Chlorophyll_Content': 34.75,
    'Electrochemical_Signal': 0.99,
}

TRAINING_FEATURES = ['Soil_Temperature', 'Humidity', 'Soil_Moisture', 'Light_Intensity',
                     'Soil_pH', 'Nitrogen_Level', 'Phosphorus_Level', 'Potassium_Level',
                     'Ambient_Temperature', 'Chlorophyll_Content', 'Electrochemical_Signal']


def normalize_columns(df):
    out = df.copy()
    for src, dst in COLUMN_MAP.items():
        if src in out.columns and dst not in out.columns:
            out[dst] = out[src]
    for col, default in DEFAULT_FEATURES.items():
        if col not in out.columns:
            out[col] = default
    if 'created_at' in out.columns:
        out['created_at'] = pd.to_datetime(out['created_at'])
        out = out.sort_values('created_at')
    for col in TRAINING_FEATURES:
        if col in out.columns:
            series = pd.to_numeric(out[col], errors='coerce').ffill().bfill()
            out[col] = series.fillna(DEFAULT_FEATURES.get(col, 0))
    return out


def process_for_prediction(iot_id, df, plant_data):
    processed = normalize_columns(df)
    processed['Plant_ID'] = 1

    processed['Hour'] = processed['created_at'].dt.hour
    processed['Day'] = processed['created_at'].dt.day
    processed['Month'] = processed['created_at'].dt.month

    plant_history = normalize_columns(plant_data[iot_id])
    plant_history = plant_history.sort_values('created_at')

    if len(plant_history) >= 6:
        for feature in TRAINING_FEATURES:
            if feature in plant_history.columns:
                processed[f'{feature}_24h_avg'] = plant_history[feature].rolling(
                    window=min(6, len(plant_history))).mean().iloc[-1]

                first_idx = max(0, len(plant_history) - 6)
                time_diff = (plant_history['created_at'].iloc[-1] -
                            plant_history['created_at'].iloc[first_idx]).total_seconds()
                if time_diff > 0:
                    value_diff = (plant_history[feature].iloc[-1] -
                                plant_history[feature].iloc[first_idx])
                    processed[f'{feature}_trend'] = value_diff / time_diff
                else:
                    processed[f'{feature}_trend'] = 0
    else:
        for feature in TRAINING_FEATURES:
            if feature in processed.columns:
                processed[f'{feature}_24h_avg'] = processed[feature]
                processed[f'{feature}_trend'] = 0

    processed['Temp_Humidity_Interaction'] = processed['Soil_Temperature'] * processed['Humidity']

    if all(col in processed.columns for col in ['Nitrogen_Level', 'Phosphorus_Level', 'Potassium_Level']):
        processed['NPK_Balance'] = (processed['Nitrogen_Level'] +
                                   processed['Phosphorus_Level'] +
                                   processed['Potassium_Level']) / 3
        if processed['NPK_Balance'].iloc[0] > 0:
            processed['NPK_Ratio_N'] = processed['Nitrogen_Level'] / processed['NPK_Balance']
            processed['NPK_Ratio_P'] = processed['Phosphorus_Level'] / processed['NPK_Balance']
            processed['NPK_Ratio_K'] = processed['Potassium_Level'] / processed['NPK_Balance']
        else:
            processed['NPK_Ratio_N'] = 0
            processed['NPK_Ratio_P'] = 0
            processed['NPK_Ratio_K'] = 0
    else:
        processed['NPK_Balance'] = 0
        processed['NPK_Ratio_N'] = 0
        processed['NPK_Ratio_P'] = 0
        processed['NPK_Ratio_K'] = 0

    if len(plant_history) >= 10:
        moisture_q25 = plant_history['Soil_Moisture'].quantile(0.25)
        temp_q25 = plant_history['Soil_Temperature'].quantile(0.25)
        temp_q75 = plant_history['Soil_Temperature'].quantile(0.75)
        light_q25 = plant_history['Light_Intensity'].quantile(0.25)

        processed['Light_Stress'] = (processed['Light_Intensity'] < light_q25).astype(int)
        processed['Moisture_Stress'] = (processed['Soil_Moisture'] < moisture_q25).astype(int)
        processed['Temperature_Stress'] = ((processed['Soil_Temperature'] > temp_q75) |
                                        (processed['Soil_Temperature'] < temp_q25)).astype(int)
    else:
        processed['Moisture_Stress'] = 0
        processed['Temperature_Stress'] = 0
        processed['Light_Stress'] = 0

    return processed

def process_for_lstm(df):
    processed = normalize_columns(df)

    processed['Hour'] = processed['created_at'].dt.hour
    processed['Day'] = processed['created_at'].dt.day
    processed['Month'] = processed['created_at'].dt.month
    processed['DayOfWeek'] = processed['created_at'].dt.dayofweek

    processed['Hour_sin'] = np.sin(processed['Hour'] * (2 * np.pi / 24))
    processed['Hour_cos'] = np.cos(processed['Hour'] * (2 * np.pi / 24))
    processed['Day_sin'] = np.sin(processed['Day'] * (2 * np.pi / 31))
    processed['Day_cos'] = np.cos(processed['Day'] * (2 * np.pi / 31))
    processed['Month_sin'] = np.sin(processed['Month'] * (2 * np.pi / 12))
    processed['Month_cos'] = np.cos(processed['Month'] * (2 * np.pi / 12))

    processed['Moisture_Temp_Interaction'] = processed['Soil_Moisture'] * processed['Soil_Temperature'] / 100
    processed['Humidity_Temp_Interaction'] = processed['Humidity'] * processed['Ambient_Temperature'] / 100

    if all(col in processed.columns for col in ['Nitrogen_Level', 'Phosphorus_Level', 'Potassium_Level']):
        processed['NPK_Balance'] = (processed['Nitrogen_Level'] + processed['Phosphorus_Level'] +
                                  processed['Potassium_Level'])
        processed['N_P_Ratio'] = processed['Nitrogen_Level'] / processed['Phosphorus_Level'].replace(0, np.nan)
        processed['N_K_Ratio'] = processed['Nitrogen_Level'] / processed['Potassium_Level'].replace(0, np.nan)
        processed[['N_P_Ratio', 'N_K_Ratio']] = processed[['N_P_Ratio', 'N_K_Ratio']].fillna(0)

    for feature in ['Soil_Moisture', 'Soil_Temperature', 'Humidity', 'Light_Intensity']:
        if feature in processed.columns:
            processed[f'{feature}_rolling_mean'] = processed[feature].rolling(
                window=min(3, len(processed)), min_periods=1).mean()
            processed[f'{feature}_change_rate'] = (processed[feature].pct_change()
                                                   .replace([np.inf, -np.inf], 0).fillna(0))

    return processed

def prepare_lstm_sequence(iot_id, plant_data, model_service):
    if not model_service.model_config or not model_service.feature_columns or not model_service.feature_scaler:
        print("LSTM model not properly loaded")
        return None

    try:
        sequence_length = model_service.get_sequence_length()

        plant_history = plant_data[iot_id].copy()
        plant_history['created_at'] = pd.to_datetime(plant_history['created_at'])
        plant_history = plant_history.sort_values('created_at')

        if len(plant_history) < sequence_length:
            print(f"Not enough history for plant {iot_id} to create sequence")
            return None

        sequence_data = plant_history.iloc[-sequence_length:].copy()

        sequence_data = process_for_lstm(sequence_data)

        X = np.zeros((1, sequence_length, len(model_service.feature_columns)))

        for i, feature in enumerate(model_service.feature_columns):
            if feature in sequence_data.columns:
                X[0, :, i] = sequence_data[feature].values

        X_reshaped = X.reshape(-1, X.shape[-1])
        X_scaled = model_service.feature_scaler.transform(X_reshaped)
        X_scaled = np.clip(X_scaled, 0.0, 1.0)
        X_scaled = X_scaled.reshape(X.shape)

        return X_scaled

    except Exception as e:
        print(f"Error preparing LSTM sequence: {e}")
        return None
