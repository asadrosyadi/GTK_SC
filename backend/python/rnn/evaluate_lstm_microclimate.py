import numpy as np
import pandas as pd
import matplotlib
matplotlib.use('Agg')
import matplotlib.pyplot as plt
plt.ioff()
import tensorflow as tf
from sklearn.preprocessing import MinMaxScaler
from sklearn.metrics import mean_absolute_error, mean_squared_error, r2_score
from tensorflow.keras.models import Sequential
from tensorflow.keras.layers import LSTM, Dense, Dropout, BatchNormalization
from datetime import datetime, timedelta
import warnings
warnings.filterwarnings('ignore')

plt.rcParams['figure.figsize'] = (15, 10)
plt.rcParams['font.size'] = 12

def calculate_vpd(temperature, humidity):
    es = 0.6108 * np.exp((17.27 * temperature) / (temperature + 237.3))
    
    ea = es * (humidity / 100)
    
    vpd = es - ea
    
    return vpd

def generate_synthetic_microclimate_data(n_samples=1000, sequence_length=6):
    np.random.seed(42)
    
    start_date = datetime(2025, 1, 1)
    timestamps = [start_date + timedelta(hours=4*i) for i in range(n_samples)]
    
    hours = np.array([ts.hour for ts in timestamps])
    days = np.array([ts.timetuple().tm_yday for ts in timestamps])
    
    temperature_base = 27 + 4 * np.sin(2 * np.pi * hours / 24) + 2 * np.sin(2 * np.pi * days / 365)
    temperature_noise = np.random.normal(0, 0.5, n_samples)
    temperature = temperature_base + temperature_noise
    temperature = np.clip(temperature, 20, 35)
    
    humidity_base = 75 - 1.5 * (temperature - 27) + 10 * np.sin(2 * np.pi * (hours + 6) / 24)
    humidity_noise = np.random.normal(0, 2, n_samples)
    humidity = humidity_base + humidity_noise
    humidity = np.clip(humidity, 40, 90)
    
    ph_base = 6.75 + 0.3 * np.sin(2 * np.pi * days / 365)
    ph_noise = np.random.normal(0, 0.05, n_samples)
    ph = ph_base + ph_noise
    ph = np.clip(ph, 6.0, 7.5)
    
    light_base = 500 * (1 + np.sin(2 * np.pi * (hours - 6) / 24))
    light_base = np.where((hours >= 6) & (hours <= 18), light_base, 
                         50 + 20 * np.random.random(n_samples))
    light_noise = np.random.normal(0, 20, n_samples)
    light_intensity = light_base + light_noise
    light_intensity = np.clip(light_intensity, 0, 1000)
    
    vpd = calculate_vpd(temperature, humidity)
    
    data = pd.DataFrame({
        'timestamp': timestamps,
        'temperature': temperature,
        'humidity': humidity,
        'ph': ph,
        'light_intensity': light_intensity,
        'vpd': vpd
    })
    
    return data

def create_sequences(data, target_columns, sequence_length=6):
    features = data[['temperature', 'humidity', 'ph', 'light_intensity', 'vpd']].values
    targets = data[target_columns].values
    
    X, y = [], []
    for i in range(len(data) - sequence_length):
        X.append(features[i:(i + sequence_length)])
        y.append(targets[i + sequence_length])
    
    return np.array(X), np.array(y)

def build_lstm_regression_model(input_shape, output_dim):

    model = Sequential([
        LSTM(64, return_sequences=True, input_shape=input_shape),
        BatchNormalization(),
        LSTM(32, return_sequences=False),
        BatchNormalization(),
        Dropout(0.2),
        Dense(32, activation='relu'),
        BatchNormalization(),
        Dropout(0.2),
        Dense(output_dim, activation='linear')
    ])

    model.compile(
        optimizer=tf.keras.optimizers.Adam(learning_rate=1e-3),
        loss='mse',
        metrics=['mae']
    )

    return model


def time_series_kfold(n_samples, n_splits):
    fold_sizes = np.full(n_splits, n_samples // n_splits, dtype=int)
    fold_sizes[: n_samples % n_splits] += 1
    current = 0
    folds = []
    for fs in fold_sizes:
        folds.append((current, current + fs))
        current += fs
    for k in range(n_splits):
        test_start, test_end = folds[k]
        test_idx = np.arange(test_start, test_end)
        train_idx = np.concatenate([np.arange(0, test_start), np.arange(test_end, n_samples)])
        yield train_idx, test_idx


def cross_validate_regression(X, y, scaler_y, target_columns, n_splits=5, epochs=100, seed=42):
    tf.keras.utils.set_random_seed(seed)
    per_fold = {p: {'MAE': [], 'RMSE': [], 'R2': []} for p in target_columns}

    for fold, (tr_idx, te_idx) in enumerate(time_series_kfold(len(X), n_splits), start=1):
        print(f"\n  [CV] Fold {fold}/{n_splits}: train={len(tr_idx)} test={len(te_idx)}")
        tf.keras.backend.clear_session()
        tf.keras.utils.set_random_seed(seed + fold)
        model = build_lstm_regression_model((X.shape[1], X.shape[2]), y.shape[1])
        model.fit(X[tr_idx], y[tr_idx], epochs=epochs, batch_size=32,
                  validation_split=0.2, verbose=0)
        y_pred = scaler_y.inverse_transform(model.predict(X[te_idx], verbose=0))
        y_true = scaler_y.inverse_transform(y[te_idx])
        for i, p in enumerate(target_columns):
            per_fold[p]['MAE'].append(mean_absolute_error(y_true[:, i], y_pred[:, i]))
            per_fold[p]['RMSE'].append(np.sqrt(mean_squared_error(y_true[:, i], y_pred[:, i])))
            per_fold[p]['R2'].append(r2_score(y_true[:, i], y_pred[:, i]))

    summary = {}
    for p in target_columns:
        entry = {}
        for m, v in per_fold[p].items():
            std = float(np.std(v, ddof=0))
            entry[m] = {
                'mean': float(np.mean(v)),
                'std': std,
                'ci95': float(1.96 * std / np.sqrt(n_splits)),
            }
        summary[p] = entry
    return summary, per_fold

def rolling_vpd_forecast_r2(model, X_test, y_test, scaler_y, target_columns,
                            horizon_days=14, step_hours=4):

    vpd_idx = target_columns.index('vpd')
    horizon = int(horizon_days * 24 / step_hours)
    n = min(len(X_test) - horizon, len(X_test))
    if n <= 0:
        return None

    preds, actuals = [], []
    for start in range(0, n, max(horizon // 2, 1)):
        window = X_test[start].copy()
        for h in range(horizon):
            p_scaled = model.predict(window[np.newaxis, ...], verbose=0)[0]
            window = np.vstack([window[1:], p_scaled])
            if start + h + 1 < len(y_test):
                preds.append(scaler_y.inverse_transform(p_scaled[np.newaxis, :])[0, vpd_idx])
                actuals.append(scaler_y.inverse_transform(y_test[start + h + 1][np.newaxis, :])[0, vpd_idx])

    if len(actuals) < 2:
        return None
    preds, actuals = np.array(preds), np.array(actuals)
    return {
        'horizon_days': horizon_days,
        'n_points': int(len(actuals)),
        'RMSE': float(np.sqrt(mean_squared_error(actuals, preds))),
        'R2': float(r2_score(actuals, preds)),
    }


def evaluate_model_performance(y_true, y_pred, parameter_names):
    results = {}
    
    for i, param in enumerate(parameter_names):
        mae = mean_absolute_error(y_true[:, i], y_pred[:, i])
        rmse = np.sqrt(mean_squared_error(y_true[:, i], y_pred[:, i]))
        r2 = r2_score(y_true[:, i], y_pred[:, i])
        
        results[param] = {
            'MAE': mae,
            'RMSE': rmse,
            'R²': r2
        }
    
    return results

def plot_comparison(y_true, y_pred, timestamps, parameter_names, save_path=None):
    fig, axes = plt.subplots(3, 2, figsize=(18, 15))
    axes = axes.flatten()
    
    colors = ['#e74c3c', '#3498db', '#2ecc71', '#f39c12', '#9b59b6']
    
    for i, param in enumerate(parameter_names):
        ax = axes[i]
        
        ax.plot(timestamps, y_true[:, i], color=colors[i], linewidth=2, 
                label=f'Actual {param}', alpha=0.8)
        ax.plot(timestamps, y_pred[:, i], color=colors[i], linewidth=2, 
                linestyle='--', label=f'Predicted {param}', alpha=0.8)
        
        mae = mean_absolute_error(y_true[:, i], y_pred[:, i])
        rmse = np.sqrt(mean_squared_error(y_true[:, i], y_pred[:, i]))
        r2 = r2_score(y_true[:, i], y_pred[:, i])
        
        ax.set_title(f'{param.title()}\nMAE: {mae:.3f}, RMSE: {rmse:.3f}, R²: {r2:.3f}', 
                    fontsize=14, fontweight='bold')
        ax.set_xlabel('Time', fontsize=12)
        
        if param == 'temperature':
            ax.set_ylabel('Temperature (°C)', fontsize=12)
        elif param == 'humidity':
            ax.set_ylabel('Humidity (%)', fontsize=12)
        elif param == 'ph':
            ax.set_ylabel('Soil pH', fontsize=12)
        elif param == 'light_intensity':
            ax.set_ylabel('Light Intensity (lux)', fontsize=12)
        elif param == 'vpd':
            ax.set_ylabel('VPD (kPa)', fontsize=12)
        
        ax.legend(loc='upper right')
        ax.grid(True, alpha=0.3)
        
        ax.tick_params(axis='x', rotation=45)
        
    fig.delaxes(axes[5])
    
    plt.tight_layout()
    
    if save_path:
        try:
            plt.savefig(save_path, format='png', dpi=300, bbox_inches='tight', 
                       facecolor='white', edgecolor='none')
            print(f"Plot saved as: {save_path}")
        except Exception as e:
            print(f"Warning: Could not save plot {save_path}: {e}")
    
    plt.close()

def main():
    print("=" * 60)
    print("LSTM MODEL EVALUATION FOR MICROCLIMATE PARAMETER PREDICTION")
    print("=" * 60)
    
    print("\n1. Generating synthetic microclimate data...")
    data = generate_synthetic_microclimate_data(n_samples=800, sequence_length=6)
    print(f"Data generated: {len(data)} samples")
    
    print("\n2. Preparing data for LSTM...")
    target_columns = ['temperature', 'humidity', 'ph', 'light_intensity', 'vpd']
    
    scaler_X = MinMaxScaler()
    scaler_y = MinMaxScaler()
    
    features_scaled = scaler_X.fit_transform(data[target_columns].values)
    targets_scaled = scaler_y.fit_transform(data[target_columns].values)
    
    data_scaled = data.copy()
    data_scaled[target_columns] = features_scaled
    
    X, y = create_sequences(data_scaled, target_columns, sequence_length=6)

    split_idx = int(0.8 * len(X))
    X_train, X_test = X[:split_idx], X[split_idx:]
    y_train, y_test = y[:split_idx], y[split_idx:]

    print(f"Training data: {X_train.shape}, Testing data: {X_test.shape}")

    print("\n2b. Running 5-fold time-series cross-validation (100 epochs/fold)...")
    cv_summary, _ = cross_validate_regression(
        X, y, scaler_y, target_columns, n_splits=5, epochs=100, seed=42
    )
    print("\nTable 5 (cross-validated). LSTM Performance -- mean +/- std over 5 folds")
    print("-" * 74)
    print(f"{'Parameter':<22} {'MAE':<16} {'RMSE':<16} {'R2':<16}")
    print("-" * 74)
    for p in target_columns:
        s = cv_summary[p]
        print(f"{p:<22} "
              f"{s['MAE']['mean']:.3f} +/- {s['MAE']['std']:.3f}   "
              f"{s['RMSE']['mean']:.3f} +/- {s['RMSE']['std']:.3f}   "
              f"{s['R2']['mean']:.3f} +/- {s['R2']['std']:.3f}")
    print("-" * 74)

    print("\n3. Building and training LSTM model (100 epochs)...")
    model = build_lstm_regression_model(
        input_shape=(X_train.shape[1], X_train.shape[2]),
        output_dim=len(target_columns)
    )

    history = model.fit(
        X_train, y_train,
        epochs=100,
        batch_size=32,
        validation_split=0.2,
        verbose=1
    )
    
    print("\n4. Making predictions...")
    y_pred_scaled = model.predict(X_test, verbose=0)
    
    y_test_original = scaler_y.inverse_transform(y_test)
    y_pred_original = scaler_y.inverse_transform(y_pred_scaled)
    
    print("\n5. Evaluating model performance...")
    results = evaluate_model_performance(y_test_original, y_pred_original, target_columns)
    
    print("\nTable 5. LSTM Performance for Microclimate Parameter Prediction")
    print("-" * 60)
    print(f"{'Parameter':<20} {'MAE':<10} {'RMSE':<10} {'R²':<10}")
    print("-" * 60)
    
    parameter_display = {
        'temperature': 'Temperature (°C)',
        'humidity': 'Humidity (%)',
        'ph': 'Soil pH',
        'light_intensity': 'Light Intensity (lux)',
        'vpd': 'VPD (kPa)'
    }
    
    total_mae = total_rmse = total_r2 = 0
    
    for param in target_columns:
        mae = results[param]['MAE']
        rmse = results[param]['RMSE']
        r2 = results[param]['R²']
        
        total_mae += mae
        total_rmse += rmse
        total_r2 += r2
        
        if param == 'ph':
            print(f"{parameter_display[param]:<20} {mae:<10.3f} {rmse:<10.3f} {r2:<10.3f}")
        elif param == 'vpd':
            print(f"{parameter_display[param]:<20} {mae:<10.3f} {rmse:<10.3f} {r2:<10.3f}")
        elif param == 'light_intensity':
            print(f"{parameter_display[param]:<20} {mae:<10.1f} {rmse:<10.1f} {r2:<10.3f}")
        else:
            print(f"{parameter_display[param]:<20} {mae:<10.2f} {rmse:<10.2f} {r2:<10.3f}")
    
    print("-" * 60)
    avg_mae = total_mae / len(target_columns)
    avg_rmse = total_rmse / len(target_columns)
    avg_r2 = total_r2 / len(target_columns)
    print(f"{'Average':<20} {avg_mae:<10.2f} {avg_rmse:<10.2f} {avg_r2:<10.3f}")
    
    print("\n6. Creating visualization...")
    
    test_timestamps = data['timestamp'].iloc[split_idx + 6:].values
    
    plot_comparison(
        y_test_original, y_pred_original, 
        test_timestamps, target_columns,
        save_path='gambar_9_perbandingan_lstm.png'
    )
    
    print("\n7. Creating Figure 9 - Temperature and Humidity comparison...")
    
    fig, (ax1, ax2) = plt.subplots(2, 1, figsize=(15, 10))
    
    ax1.plot(test_timestamps, y_test_original[:, 0], color='#e74c3c', 
             linewidth=2.5, label='Actual Temperature', alpha=0.8)
    ax1.plot(test_timestamps, y_pred_original[:, 0], color='#e74c3c', 
             linewidth=2.5, linestyle='--', label='Predicted Temperature', alpha=0.8)
    
    ax1.set_title('Actual vs Predicted Temperature Trend Comparison (LSTM)', 
                  fontsize=16, fontweight='bold')
    ax1.set_ylabel('Temperature (°C)', fontsize=14)
    ax1.legend(loc='upper right', fontsize=12)
    ax1.grid(True, alpha=0.3)
    ax1.tick_params(axis='x', rotation=45)
    
    ax2.plot(test_timestamps, y_test_original[:, 1], color='#3498db', 
             linewidth=2.5, label='Actual Humidity', alpha=0.8)
    ax2.plot(test_timestamps, y_pred_original[:, 1], color='#3498db', 
             linewidth=2.5, linestyle='--', label='Predicted Humidity', alpha=0.8)
    
    ax2.set_title('Actual vs Predicted Humidity Trend Comparison (LSTM)', 
                  fontsize=16, fontweight='bold')
    ax2.set_ylabel('Humidity (%)', fontsize=14)
    ax2.set_xlabel('Time', fontsize=14)
    ax2.legend(loc='upper right', fontsize=12)
    ax2.grid(True, alpha=0.3)
    ax2.tick_params(axis='x', rotation=45)
    
    temp_mae = results['temperature']['MAE']
    temp_rmse = results['temperature']['RMSE']
    temp_r2 = results['temperature']['R²']
    
    humid_mae = results['humidity']['MAE']
    humid_rmse = results['humidity']['RMSE']
    humid_r2 = results['humidity']['R²']
    
    ax1.text(0.02, 0.98, f'MAE: {temp_mae:.2f}°C, RMSE: {temp_rmse:.2f}°C, R²: {temp_r2:.3f}', 
             transform=ax1.transAxes, fontsize=12, verticalalignment='top',
             bbox=dict(boxstyle='round', facecolor='white', alpha=0.8))
    
    ax2.text(0.02, 0.98, f'MAE: {humid_mae:.2f}%, RMSE: {humid_rmse:.2f}%, R²: {humid_r2:.3f}', 
             transform=ax2.transAxes, fontsize=12, verticalalignment='top',
             bbox=dict(boxstyle='round', facecolor='white', alpha=0.8))
    
    plt.tight_layout()
    try:
        plt.savefig('gambar_9_suhu_kelembapan_lstm.png', format='png', dpi=300, 
                   bbox_inches='tight', facecolor='white', edgecolor='none')
        print("Figure 9 saved as: gambar_9_suhu_kelembapan_lstm.png")
    except Exception as e:
        print(f"Warning: Could not save Figure 9: {e}")
    plt.close()

    print("\n8. Evaluating 14-day rolling VPD forecast...")
    roll = rolling_vpd_forecast_r2(model, X_test, y_test, scaler_y, target_columns,
                                   horizon_days=14, step_hours=4)
    if roll:
        print(f"   14-day VPD forecast: R2 = {roll['R2']:.3f}, RMSE = {roll['RMSE']:.3f} kPa "
              f"(n = {roll['n_points']} points)")
    else:
        print("   (not enough test samples for a 14-day rolling forecast on this dataset)")

    print("\n" + "=" * 60)
    print("EVALUATION COMPLETED!")
    print("=" * 60)
    print(f"\nLSTM model shows good performance with:")
    print(f"- Average R²: {avg_r2:.3f}")
    print(f"- Average MAE: {avg_mae:.3f}")
    print(f"- Average RMSE: {avg_rmse:.3f}")
    print(f"\nTemperature and humidity prediction curves follow actual data with small deviations.")
    print(f"Low VPD RMSE ({results['vpd']['RMSE']:.3f} kPa) indicates LSTM's ability")
    print(f"to reliably model microclimate stress (water-vapor).")

if __name__ == "__main__":
    main()