import pandas as pd
import numpy as np
import math
from datetime import datetime, timedelta
from app.utils.data_processor import process_for_prediction, prepare_lstm_sequence


class ForecastService:
    
    def __init__(self, model_service, data_service):
        self.model_service = model_service
        self.data_service = data_service
    
    def generate_forecast(self, iot_id, days=3):
        if self.model_service.lstm_model is not None:
            lstm_forecast = self.generate_lstm_forecast(iot_id, days)
            if lstm_forecast is not None:
                return lstm_forecast
        
        return self.generate_traditional_forecast(iot_id, days)
    
    def generate_lstm_forecast(self, iot_id, days=3):
        try:
            X_sequence = prepare_lstm_sequence(
                iot_id, 
                self.data_service.plant_data, 
                self.model_service
            )
            
            if X_sequence is None:
                print("Could not prepare LSTM sequence.")
                return None
                
            plant_history = self.data_service.get_plant_data(iot_id)
            plant_history['created_at'] = pd.to_datetime(plant_history['created_at'])
            plant_history = plant_history.sort_values('created_at')
            latest_data = plant_history.iloc[-1:].copy()
            timestamp = latest_data['created_at'].iloc[0]
                
            forecast = []
            
            current_entry = {
                'date': timestamp.strftime("%Y-%m-%d %H:%M:%S"),
                'forecast_type': 'current',
                'soil_temp': self._last_valid(plant_history, 'soil_temp'),
                'humidity': self._last_valid(plant_history, 'humidity'),
                'soil_moisture': self._last_valid(plant_history, 'soil_moisture')
            }
            
            prediction = self.model_service.predict_lstm(X_sequence)
            if prediction:
                current_entry['predicted_health'] = prediction['predicted_health']
                current_entry['confidence'] = prediction['confidence']
            
            for src, dst in [('temperature', 'temperature'), 
                         ('light_intensity', 'light_intensity'),
                         ('ph', 'ph'),
                         ('Nitrogen_Level', 'nitrogen'),
                         ('Phosphorus_Level', 'phosphorus'),
                         ('Potassium_Level', 'potassium')]:
                if src in latest_data.columns:
                    current_entry[dst] = self._last_valid(plant_history, src)
            
            forecast.append(current_entry)
            
            working_history = plant_history.copy()
            profiles = self._hourly_profiles(plant_history)

            current_values = {
                'soil_temp': self._last_valid(plant_history, 'soil_temp'),
                'humidity': self._last_valid(plant_history, 'humidity'),
                'soil_moisture': self._last_valid(plant_history, 'soil_moisture'),
                'light_intensity': self._last_valid(plant_history, 'light_intensity'),
                'temperature': self._last_valid(plant_history, 'temperature'),
                'ph': self._last_valid(plant_history, 'ph'),
                'Nitrogen_Level': self._last_valid(plant_history, 'Nitrogen_Level'),
                'Phosphorus_Level': self._last_valid(plant_history, 'Phosphorus_Level'),
                'Potassium_Level': self._last_valid(plant_history, 'Potassium_Level')
            }
            
            
            for i in range(1, days * 6):
                timestamp = timestamp + pd.Timedelta(hours=4)
                
                    
                self._project_values(current_values, profiles, timestamp.hour)

                new_row = latest_data.copy()
                new_row['created_at'] = timestamp
                for feature, value in current_values.items():
                    new_row[feature] = value
                working_history = pd.concat([working_history, new_row], ignore_index=True)

                current_sequence = prepare_lstm_sequence(
                    iot_id, {iot_id: working_history}, self.model_service
                )
                if current_sequence is None:
                    continue
                pred = self.model_service.predict_lstm(current_sequence)
                if not pred:
                    continue
                    
                forecast_entry = {
                    'date': timestamp.strftime("%Y-%m-%d %H:%M:%S"),
                    'forecast_type': 'forecast',
                    'soil_temp': self._clean(current_values['soil_temp']),
                    'humidity': self._clean(current_values['humidity']),
                    'soil_moisture': self._clean(current_values['soil_moisture']),
                    'predicted_health': pred['predicted_health'],
                    'confidence': pred['confidence']
                }
                
                for attr, key in [('temperature', 'temperature'), 
                                ('light_intensity', 'light_intensity'),
                                ('ph', 'ph'),
                                ('Nitrogen_Level', 'nitrogen'),
                                ('Phosphorus_Level', 'phosphorus'),
                                ('Potassium_Level', 'potassium')]:
                    if attr in current_values:
                        forecast_entry[key] = self._clean(current_values[attr])
                        
                forecast.append(forecast_entry)
            
            return forecast
            
        except Exception as e:
            print(f"Error in LSTM forecasting: {e}")
            return None
    
    def generate_traditional_forecast(self, iot_id, days=3):
        plant_history = self.data_service.get_plant_data(iot_id)
        if plant_history is None:
            return None
            
        plant_history['created_at'] = pd.to_datetime(plant_history['created_at'])
        plant_history = plant_history.sort_values('created_at')

        latest_data = plant_history.iloc[-1:].copy()
        timestamp = latest_data['created_at'].iloc[0]

        profiles = self._hourly_profiles(plant_history)

        forecast = []
        
        current_entry = {
            'date': timestamp.strftime("%Y-%m-%d %H:%M:%S"),
            'forecast_type': 'current',
            'soil_temp': self._last_valid(plant_history, 'soil_temp'),
            'humidity': self._last_valid(plant_history, 'humidity'),
            'soil_moisture': self._last_valid(plant_history, 'soil_moisture')
        }
        
        processed_data = process_for_prediction(
            iot_id, latest_data, self.data_service.plant_data
        )
        
        prediction = self.model_service.predict_traditional(processed_data)
        if prediction:
            current_entry['predicted_health'] = prediction['predicted_health']
            current_entry['confidence'] = prediction['confidence']
        
        for src, dst in [('temperature', 'temperature'), 
                        ('light_intensity', 'light_intensity'),
                        ('ph', 'ph'),
                        ('Nitrogen_Level', 'nitrogen'),
                        ('Phosphorus_Level', 'phosphorus'),
                        ('Potassium_Level', 'potassium')]:
            if src in latest_data.columns:
                current_entry[dst] = self._last_valid(plant_history, src)
        
        forecast.append(current_entry)

        current_values = {
            'soil_temp': self._last_valid(plant_history, 'soil_temp'),
            'humidity': self._last_valid(plant_history, 'humidity'),
            'soil_moisture': self._last_valid(plant_history, 'soil_moisture'),
            'light_intensity': self._last_valid(plant_history, 'light_intensity'),
            'temperature': self._last_valid(plant_history, 'temperature'),
            'ph': self._last_valid(plant_history, 'ph'),
            'Nitrogen_Level': self._last_valid(plant_history, 'Nitrogen_Level'),
            'Phosphorus_Level': self._last_valid(plant_history, 'Phosphorus_Level'),
            'Potassium_Level': self._last_valid(plant_history, 'Potassium_Level')
        }

        
        for i in range(1, days * 6):
            timestamp = timestamp + pd.Timedelta(hours=4)
            
            new_data = latest_data.copy()
            new_data['created_at'] = timestamp
            
            new_data['Hour'] = timestamp.hour
            new_data['Day'] = timestamp.day
            new_data['Month'] = timestamp.month
            
            
            self._project_values(current_values, profiles, timestamp.hour)
            
            for feature, value in current_values.items():
                if feature in new_data.columns:
                    new_data[feature] = value

            processed_data = process_for_prediction(
                iot_id, new_data, self.data_service.plant_data
            )

            prediction = self.model_service.predict_traditional(processed_data)
            if not prediction:
                continue

            forecast_entry = {
                'date': timestamp.strftime("%Y-%m-%d %H:%M:%S"),
                'forecast_type': 'forecast',
                'soil_temp': self._clean(current_values['soil_temp']),
                'humidity': self._clean(current_values['humidity']),
                'soil_moisture': self._clean(current_values['soil_moisture']),
                'predicted_health': prediction['predicted_health'],
                'confidence': prediction['confidence']
            }
            
            for src, dst in [('temperature', 'temperature'), 
                            ('light_intensity', 'light_intensity'),
                            ('ph', 'ph'),
                            ('Nitrogen_Level', 'nitrogen'),
                            ('Phosphorus_Level', 'phosphorus'),
                            ('Potassium_Level', 'potassium')]:
                if src in current_values:
                    forecast_entry[dst] = self._clean(current_values[src])
                    
            forecast.append(forecast_entry)

        return forecast
    
    def _calculate_trends(self, plant_history):
        trends = {}
        if len(plant_history) >= 6:
            first_idx = max(0, len(plant_history) - 6)
            for feature in ['soil_temp', 'humidity', 'soil_moisture', 'light_intensity',
                          'temperature', 'ph', 'Nitrogen_Level', 'Phosphorus_Level', 'Potassium_Level']:
                if feature in plant_history.columns:
                    time_diff = (plant_history['created_at'].iloc[-1] - plant_history['created_at'].iloc[first_idx]).total_seconds()
                    if time_diff > 0:
                        value_diff = plant_history[feature].iloc[-1] - plant_history[feature].iloc[first_idx]
                        trends[feature] = (value_diff / time_diff) * 3600
                    else:
                        trends[feature] = 0
        else:
            trends = {
                'soil_temp': 0.05,
                'humidity': -0.2,
                'soil_moisture': -0.4,
                'light_intensity': 0,
                'temperature': 0.1,
                'ph': 0,
                'Nitrogen_Level': -0.01,
                'Phosphorus_Level': -0.01,
                'Potassium_Level': -0.01
            }
            
        return trends
    
    @staticmethod
    def _data_iot_id(history):
        if history is None or 'iot_id' not in history.columns or history['iot_id'].dropna().empty:
            return None
        return str(history['iot_id'].dropna().iloc[-1])

    @staticmethod
    def _clean(value):
        try:
            f = float(value)
        except (TypeError, ValueError):
            return None
        return None if math.isnan(f) or math.isinf(f) else f

    @classmethod
    def _last_valid(cls, history, column):
        if history is None or column not in history.columns:
            return None
        series = pd.to_numeric(history.sort_values('created_at')[column], errors='coerce').dropna()
        return cls._clean(series.iloc[-1]) if not series.empty else None

    DIURNAL_FEATURES = ['temperature', 'humidity', 'light_intensity', 'soil_temp']

    def _hourly_profiles(self, plant_history):
        profiles = {}
        if plant_history is None or 'created_at' not in plant_history.columns:
            return profiles
        hours = pd.to_datetime(plant_history['created_at']).dt.hour
        for feature in self.DIURNAL_FEATURES:
            if feature in plant_history.columns:
                series = pd.to_numeric(plant_history[feature], errors='coerce')
                profile = series.groupby(hours).mean().dropna()
                if not profile.empty:
                    profiles[feature] = profile
        return profiles

    def _project_values(self, current_values, profiles, hour):

        for feature, profile in profiles.items():
            if feature not in current_values or profile.empty or current_values[feature] is None:
                continue
            idx = profile.index.to_numpy()
            nearest = idx[np.argmin(np.minimum(np.abs(idx - hour), 24 - np.abs(idx - hour)))]
            current_values[feature] = float(profile.loc[nearest])
        if current_values.get('humidity') is not None:
            current_values['humidity'] = max(0.0, min(100.0, current_values['humidity']))

    def health_history(self, iot_id, n=5):
        history = self.data_service.get_plant_data(iot_id)
        if history is None or self.model_service.lstm_model is None:
            return []
        history = history.copy()
        history['created_at'] = pd.to_datetime(history['created_at'])
        history = history.sort_values('created_at').reset_index(drop=True)

        ends, batch = [], []
        for end in range(max(0, len(history) - n), len(history)):
            X = prepare_lstm_sequence(iot_id, {iot_id: history.iloc[:end + 1]}, self.model_service)
            if X is not None:
                ends.append(end)
                batch.append(X[0])
        if not batch:
            return []

        probs = self.model_service.lstm_model.predict(np.stack(batch), verbose=0, batch_size=256)
        classes = list(self.model_service.label_encoder.classes_)
        rows = []
        for end, p in zip(ends, probs):
            rows.append({
                'date': history.loc[end, 'created_at'].strftime('%Y-%m-%d %H:%M'),
                'predicted_health': classes[int(p.argmax())],
                'confidence': {name: float(v) for name, v in zip(classes, p)},
            })
        return rows

    def generate_plant_forecast_data(self, iot_id, days=3):
        plant_data = self.data_service.get_plant_data(iot_id)
        if plant_data is None or len(plant_data) < 6:
            return None

        try:
            forecast = self.generate_forecast(iot_id, days)
            if not forecast:
                return None

            return {
                "iot_id": self._data_iot_id(plant_data),
                "titik": iot_id,
                "forecast_generated": datetime.now().strftime("%Y-%m-%d %H:%M:%S"),
                "days_forecasted": days,
                "forecast": forecast
            }

        except Exception as e:
            print(f"Error generating forecast data: {e}")
            return None
    
    def get_plant_health_data(self, iot_id):
        plant_df = self.data_service.get_plant_data(iot_id)
        if plant_df is None or len(plant_df) == 0:
            return None
            
        try:
            plant_df = plant_df.sort_values('created_at')
            latest_data = plant_df.iloc[-1:].copy()

            latest_data = process_for_prediction(
                iot_id, latest_data, self.data_service.plant_data
            )

            prediction = self.model_service.predict_traditional(latest_data)
            if not prediction:
                print('No prediction available.')
                return None
                
            readings = {
                "soil_temperature": self._last_valid(plant_df, 'soil_temp'),
                "humidity": self._last_valid(plant_df, 'humidity'),
                "soil_moisture": self._last_valid(plant_df, 'soil_moisture')
            }
            
            additional_fields = [
                ('temperature', 'temperature'),
                ('light_intensity', 'light_intensity'),
                ('ph', 'ph'),
                ('Nitrogen_Level', 'nitrogen'),
                ('Phosphorus_Level', 'phosphorus'),
                ('Potassium_Level', 'potassium'),
                ('Chlorophyll_Content', 'chlorophyll'),
                ('Electrochemical_Signal', 'ec_signal')
            ]
            
            for orig_field, resp_field in additional_fields:
                if orig_field in latest_data.columns:
                    readings[resp_field] = self._last_valid(plant_df, orig_field)

            return {
                "iot_id": self._data_iot_id(plant_df),
                "timestamp": latest_data['created_at'].iloc[0].strftime("%Y-%m-%d %H:%M:%S"),
                "predicted_health": prediction['predicted_health'],
                "confidence": prediction['confidence'],
                "current_readings": readings
            }

        except Exception as e:
            print(f"Error generating health data: {e}")
            return None