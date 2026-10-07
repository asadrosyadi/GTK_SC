import json
import sys
from pathlib import Path

import pandas as pd
import matplotlib
matplotlib.use("Agg")
import matplotlib.pyplot as plt

HERE = Path(__file__).resolve().parent
sys.path.insert(0, str(HERE))

from app.services.model_service import ModelService      # noqa: E402
from app.services.forecast_service import ForecastService  # noqa: E402

IOT_ID = 1


class CsvDataService:

    def __init__(self, csv_path):
        df = pd.read_csv(csv_path)
        df = df[df["Plant_ID"] == IOT_ID].copy()
        df["created_at"] = pd.to_datetime(df["Timestamp"])
        df = df.sort_values("created_at")
        df["soil_temp"] = df["Soil_Temperature"]
        df["humidity"] = df["Humidity"]
        df["soil_moisture"] = df["Soil_Moisture"]
        df["light_intensity"] = df["Light_Intensity"]
        df["temperature"] = df["Ambient_Temperature"]
        df["ph"] = df["Soil_pH"]
        self.plant_data = {IOT_ID: df}

    def get_plant_data(self, iot_id):
        return self.plant_data[iot_id].copy()


def render_json_panel(entry, out_path, dpi=600):
    text = json.dumps(entry, indent=4, sort_keys=True)
    n_lines = text.count("\n") + 1

    fig, ax = plt.subplots(figsize=(8.2, 0.30 * n_lines + 1.2))
    ax.axis("off")
    ax.set_title("Encoded JSON output", fontsize=15, fontweight="bold", pad=14)

    ax.text(0.02, 0.97, text, family="DejaVu Sans Mono", fontsize=11,
            va="top", ha="left", linespacing=1.45, transform=ax.transAxes,
            bbox=dict(boxstyle="round,pad=0.8", facecolor="#f7f9fb",
                      edgecolor="#8fa8bf", linewidth=1.4))
    fig.text(0.5, 0.012,
             "Values are shown in their reporting units "
             "(°C, %, lux, pH, mg/kg); model-input scaling is applied separately.",
             ha="center", fontsize=9, style="italic", color="#444444")
    plt.tight_layout(rect=[0, 0.04, 1, 1])
    plt.savefig(out_path, dpi=dpi, bbox_inches="tight", facecolor="white")
    plt.close()


def main():
    data_service = CsvDataService(HERE / "plant_health_data.csv")
    print(f"Loaded {len(data_service.plant_data[IOT_ID])} readings for Plant_ID={IOT_ID}")

    model_service = ModelService(
        model_path=str(HERE / "data/processed/plant_health_prediction_model.joblib"),
        lstm_path=str(HERE / "config/plant_health_lstm_model.h5"),
        scaler_path=str(HERE / "config/feature_scaler.pkl"),
        encoder_path=str(HERE / "config/label_encoder.pkl"),
        features_path=str(HERE / "config/feature_columns.pkl"),
        config_path=str(HERE / "config/model_config.pkl"),
    )
    if model_service.lstm_model is None:
        raise SystemExit("LSTM model could not be loaded -- cannot produce a real output.")

    forecast_service = ForecastService(model_service, data_service)
    forecast = forecast_service.generate_lstm_forecast(IOT_ID, days=3)
    if not forecast:
        raise SystemExit("Forecast service returned no entries.")

    print(f"Forecast entries returned by the service: {len(forecast)}")

    entry = next((e for e in forecast if e.get("forecast_type") == "forecast"), forecast[0])

    with open(HERE / "figure4b_real_forecast_entry.json", "w", encoding="utf-8") as f:
        json.dump(entry, f, indent=4, sort_keys=True)

    print("\n=== REAL service output entry (verbatim) ===")
    print(json.dumps(entry, indent=4, sort_keys=True))

    out = HERE / "Figure_4b_real_json_output.png"
    render_json_panel(entry, out)
    print(f"\nSaved: {out}")
    print(f"Saved: {HERE / 'figure4b_real_forecast_entry.json'}")


if __name__ == "__main__":
    main()
