<?php

return [

    'titik' => [
        1 => [
            'label' => 'Petak 1', 'weather' => true,
            'tanggal_tanam' => env('TANGGAL_TANAM_TITIK_1'),
            'umur_varietas' => (int) env('UMUR_VARIETAS_TITIK_1', 115),
            'umur_bibit' => (int) env('UMUR_BIBIT_TITIK_1', 21),
        ],
        2 => [
            'label' => 'Petak 2', 'weather' => false,
            'tanggal_tanam' => env('TANGGAL_TANAM_TITIK_2'),
            'umur_varietas' => (int) env('UMUR_VARIETAS_TITIK_2', 115),
            'umur_bibit' => (int) env('UMUR_BIBIT_TITIK_2', 21),
        ],
    ],

    'weather_titik' => 1,

    'elevasi_stasiun' => (float) env('ELEVASI_STASIUN_M', 0),

    'weather_fields' => ['temperature', 'humidity', 'windspeed', 'rainfall', 'light_intensity', 'pressure'],
    'soil_fields' => ['ph', 'soil_moisture', 'ec', 'tds', 'soil_temp', 'Nitrogen_Level', 'Phosphorus_Level', 'Potassium_Level'],

    'sensor_forecast' => [
        'url' => trim((string) env('API_SENSOR_FORECAST', 'http://127.0.0.1:5000/sensor-forecast/')),
        'timeout' => (int) env('API_SENSOR_FORECAST_TIMEOUT', 30),
        'max_days' => (int) env('SENSOR_FORECAST_MAX_DAYS', 30),
    ],

    'admin' => [
        'name' => env('ADMIN_NAME', 'Pi'),
        'email' => env('ADMIN_EMAIL', 'admin@admin.com'),
        'password' => env('ADMIN_PASSWORD', 'admin123'),
        'iot_id' => env('ADMIN_IOT_ID', 'jTZids5M'),
        'iot_token' => env('ADMIN_IOT_TOKEN', 'lOsx0KMJSAfJpxEe'),
    ],

    'api_token_days' => (int) env('API_TOKEN_DAYS', 30),

    'detect' => [
        'url' => trim((string) env('API_DETECT', 'http://127.0.0.1:7000/detect')),
        'timeout' => (int) env('API_DETECT_TIMEOUT', 60),
    ],

    'recommendation' => [
        'url' => trim((string) env('API_RECOMMENDATION', 'http://127.0.0.1:5000/recommendation')),
        'timeout' => (int) env('API_RECOMMENDATION_TIMEOUT', 15),
    ],

];
