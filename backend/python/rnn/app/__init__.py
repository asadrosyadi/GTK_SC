from flask import Flask
from flask_cors import CORS
from flask_socketio import SocketIO
import os

LSTM_MODEL_PATH = "./config/plant_health_lstm_model.h5"
FEATURE_SCALER_PATH = "./config/feature_scaler.pkl"
LABEL_ENCODER_PATH = "./config/label_encoder.pkl"
FEATURE_COLUMNS_PATH = "./config/feature_columns.pkl"
MODEL_CONFIG_PATH = "./config/model_config.pkl"
MODEL_PATH = "./data/processed/plant_health_prediction_model.joblib"
HISTORY_URL = os.environ.get("HISTORY_URL", "http://localhost/api/sensor?iot_id=jTZids5M")

def create_app():
    flask_app = Flask(__name__)
    flask_app.config['SECRET_KEY'] = 'plant-monitoring-secret-key'
    
    CORS(flask_app, resources={r"/*": {"origins": "*"}})
    
    socketio = SocketIO(flask_app, cors_allowed_origins="*")
    
    os.makedirs("./data/processed", exist_ok=True)
    
    from app.services.data_service import DataService
    from app.services.model_service import ModelService
    from app.services.forecast_service import ForecastService
    
    data_service = DataService(HISTORY_URL)
    model_service = ModelService(
        MODEL_PATH,
        LSTM_MODEL_PATH,
        FEATURE_SCALER_PATH,
        LABEL_ENCODER_PATH,
        FEATURE_COLUMNS_PATH,
        MODEL_CONFIG_PATH
    )
    forecast_service = ForecastService(model_service, data_service)

    try:
        from recommendation import RecommendationEngine
        recommendation_engine = RecommendationEngine()
    except Exception as e:
        print(f"[Recommendation] gagal dimuat: {e}")
        recommendation_engine = None

    from app.routes.api import register_routes
    register_routes(flask_app, socketio, model_service, data_service, forecast_service,
                    recommendation_engine)
    
    class AppInstance:
        def __init__(self, flask_app, socketio):
            self.flask_app = flask_app
            self.socketio = socketio
    
    return AppInstance(flask_app, socketio)
