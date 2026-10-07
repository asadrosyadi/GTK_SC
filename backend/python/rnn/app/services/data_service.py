import pandas as pd
import json
import requests
from datetime import datetime
import os
import time

WEATHER_TITIK = 1
WEATHER_COLUMNS = ['temperature', 'humidity', 'windspeed', 'rainfall', 'light_intensity', 'pressure']


class DataService:

    @staticmethod
    def split_by_titik(df):
        if 'titik' not in df.columns:
            df['titik'] = 1
        df['titik'] = pd.to_numeric(df['titik'], errors='coerce').fillna(1).astype(int)
        df['created_at'] = pd.to_datetime(df['created_at'], errors='coerce')
        df = df.dropna(subset=['created_at'])
        for col in WEATHER_COLUMNS + ['ph', 'soil_moisture', 'ec', 'tds', 'soil_temp',
                                      'Nitrogen_Level', 'Phosphorus_Level', 'Potassium_Level']:
            if col in df.columns:
                df[col] = pd.to_numeric(df[col], errors='coerce')

        weather_cols = [c for c in WEATHER_COLUMNS if c in df.columns]
        weather = (df[df['titik'] == WEATHER_TITIK][['created_at'] + weather_cols]
                   .dropna(subset=['temperature', 'humidity'] if 'humidity' in weather_cols else weather_cols[:1])
                   .sort_values('created_at'))

        result = {}
        for titik, part in df.groupby('titik'):
            part = part.sort_values('created_at').reset_index(drop=True)
            if titik != WEATHER_TITIK and not weather.empty:
                part = pd.merge_asof(part.drop(columns=weather_cols, errors='ignore'), weather,
                                     on='created_at', direction='nearest')
            result[int(titik)] = part
        return result

    def __init__(self, history_source):
        self.history_source = history_source
        self.plant_data = {}
        self.last_loaded = 0
        self._load_history()

    def refresh(self, max_age_seconds=60):
        if not self.history_source.startswith("http"):
            return
        if not self.plant_data or time.time() - self.last_loaded > max_age_seconds:
            self._load_history()

    def _load_history(self):
        try:
            if self.history_source.startswith("http://") or self.history_source.startswith("https://"):
                response = requests.get(self.history_source, timeout=15)
                response.raise_for_status()
                raw_data = response.json()
                self.last_loaded = time.time()

                if isinstance(raw_data, list) and raw_data:
                    self.plant_data = self.split_by_titik(pd.DataFrame(raw_data))
                elif isinstance(raw_data, list):
                    self.plant_data = {}
                elif isinstance(raw_data, dict):
                    for iot_id, readings in raw_data.items():
                        self.plant_data[int(iot_id)] = pd.DataFrame(readings)

                print(f"[DataService] Loaded history from URL for {len(self.plant_data)} plants")

            else:
                if os.path.exists(self.history_source):
                    with open(self.history_source, 'r') as f:
                        history_data = json.load(f)
                        for iot_id, readings in history_data.items():
                            self.plant_data[int(iot_id)] = pd.DataFrame(readings)
                    print(f"[DataService] Loaded history from file for {len(self.plant_data)} plants")

        except Exception as e:
            print(f"[DataService] Error loading history: {e}")
    
    def save_history(self):
        if self.history_source.startswith("http"):
            print("[DataService] Skipping save_history: source is URL")
            return

        try:
            history_data = {}
            for pid, pdata in self.plant_data.items():
                pdata_copy = pdata.copy()
                if 'created_at' in pdata_copy.columns:
                    pdata_copy['created_at'] = pdata_copy['created_at'].dt.strftime("%Y-%m-%d %H:%M:%S.%f")
                history_data[str(pid)] = pdata_copy.to_dict('records')

            with open(self.history_source, 'w') as f:
                json.dump(history_data, f)
            print(f"[DataService] History saved: {len(history_data)} plants")

        except Exception as e:
            print(f"[DataService] Error saving history: {e}")

    def add_sensor_reading(self, data):
        iot_id = data['iot_id']
        df_row = pd.DataFrame([data])

        if iot_id not in self.plant_data:
            self.plant_data[iot_id] = df_row
        else:
            self.plant_data[iot_id] = pd.concat([self.plant_data[iot_id], df_row], ignore_index=True)

        if 'created_at' in self.plant_data[iot_id].columns:
            self.plant_data[iot_id]['created_at'] = pd.to_datetime(self.plant_data[iot_id]['created_at'], errors='coerce')
            cutoff = pd.Timestamp.now() - pd.Timedelta(days=30)
            self.plant_data[iot_id] = self.plant_data[iot_id][
                self.plant_data[iot_id]['created_at'] > cutoff
            ]

        self.save_history()

        return iot_id
    
    def get_plant_data(self, iot_id):
        return self.plant_data.get(iot_id, None)
    
    def get_all_iot_ids(self):
        return list(self.plant_data.keys())
