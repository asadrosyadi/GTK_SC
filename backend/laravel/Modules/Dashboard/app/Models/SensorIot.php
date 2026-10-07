<?php

namespace Modules\Dashboard\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class SensorIot extends Model
{
    use HasFactory;

    protected $table = 'sensor_iots';
    protected $fillable = [
        'iot_id', 'titik', 'temperature', 'humidity', 'windspeed', 'rainfall',
        'light_intensity', 'ph', 'soil_moisture', 'ec', 'tds', 'soil_temp',
        'pressure', 'feromon', 'battery_level', 'signal_strength',
        'Nitrogen_Level', 'Phosphorus_Level', 'Potassium_Level', 'is_dummy',
    ];

    protected $casts = ['is_dummy' => 'boolean'];

}
