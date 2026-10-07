import time

from flask import request, jsonify


def register_routes(app, socketio, model_service, data_service, forecast_service, recommendation_engine=None):

    health_cache = {}
    HEALTH_TTL = 60

    def current_health(titik):
        cached = health_cache.get(titik)
        if cached and time.time() - cached[0] < HEALTH_TTL:
            return cached[1]
        data_service.refresh()
        health = None
        plant_df = data_service.get_plant_data(titik)
        if plant_df is not None and len(plant_df) >= 6:
            forecast = forecast_service.generate_plant_forecast_data(titik, 1)
            if forecast and forecast.get('forecast'):
                health = forecast['forecast'][0].get('confidence')
        health_cache[titik] = (time.time(), health)
        return health

    @app.route('/', methods=['GET'])
    def health_check():
        return jsonify({
            "status": "healthy",
            "model_loaded": model_service.model is not None,
            "lstm_model_loaded": model_service.lstm_model is not None,
            "recommendation_model": recommendation_engine.source if recommendation_engine else None,
            "titik": data_service.get_all_iot_ids(),
        })

    @app.route('/health-history/<int:titik>', methods=['GET'])
    def health_history(titik):
        data_service.refresh()
        n = max(1, min(request.args.get('n', default=5, type=int), 3000))
        rows = forecast_service.health_history(titik, n)
        plant_df = data_service.get_plant_data(titik)
        iot_id = None
        if plant_df is not None and 'iot_id' in plant_df.columns and not plant_df['iot_id'].dropna().empty:
            iot_id = str(plant_df['iot_id'].dropna().iloc[-1])
        return jsonify({'iot_id': iot_id, 'titik': titik, 'history': rows})

    @app.route('/forecast/<int:iot_id>', methods=['GET'])
    def forecast_plant_health(iot_id):

        data_service.refresh()
        titik = request.args.get('titik', default=1, type=int)
        plant_df = data_service.get_plant_data(titik)
        if plant_df is None or len(plant_df) < 6:
            return jsonify({
                "error": f"Not enough data for titik {titik}. Need at least 6 readings."
            }), 400

        try:
            days = int(request.args.get('days', 3))
            from app.services.sensor_forecast import MAX_FORECAST_DAYS
            days = max(1, min(days, MAX_FORECAST_DAYS + 1))

            forecast_data = forecast_service.generate_plant_forecast_data(titik, days)
            if forecast_data:
                return jsonify(forecast_data)
            else:
                return jsonify({"error": "Failed to generate forecast"}), 500

        except Exception as e:
            return jsonify({"error": str(e)}), 500

    @app.route('/sensor-forecast/<int:titik>', methods=['GET'])
    def sensor_forecast(titik):

        from app.services.sensor_forecast import forecast_sensors
        data_service.refresh()
        plant_df = data_service.get_plant_data(titik)
        if plant_df is None or len(plant_df) == 0:
            return jsonify({"error": f"Belum ada data sensor untuk titik {titik}"}), 404
        days = request.args.get('days', default=3, type=int)
        try:
            result = forecast_sensors(plant_df, days=days, titik=titik)
        except Exception as e:
            return jsonify({"error": str(e)}), 500
        if result is None:
            return jsonify({"error": f"Belum ada data sensor untuk titik {titik}"}), 404
        return jsonify(result)

    @app.route('/recommendation', methods=['POST'])
    def recommendation():

        if recommendation_engine is None:
            return jsonify({"error": "Recommendation engine not loaded"}), 503
        snapshot = request.get_json(silent=True) or {}
        if not snapshot.get('titik'):
            return jsonify({"error": "Snapshot must contain 'titik'"}), 400
        try:
            return jsonify(recommendation_engine.recommend(snapshot, health_provider=current_health))
        except Exception as e:
            return jsonify({"error": str(e)}), 500

    @app.route('/recommendation/model', methods=['GET'])
    def recommendation_model():
        if recommendation_engine is None:
            return jsonify({"error": "Recommendation engine not loaded"}), 503
        return jsonify({"source": recommendation_engine.source, **recommendation_engine.meta})
