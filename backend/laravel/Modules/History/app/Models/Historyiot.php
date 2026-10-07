<?php

namespace Modules\History\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Historyiot extends Model
{
    use HasFactory;

    protected $table = 'sensor_iots';
    protected $fillable = ['timestamp', 'temperature', 'humidity', 'windspeed', 'rainfall', 'light_intensity', 'ph', 'soil_moisture', 'ec', 'tds', 'soil_temp', 'pressure', 'feromon', 'battery_level', 'signal_strenght', 'Nitrogen_Level', 'Phosphorus_Level', 'Potassium_Level',  'iot_id'];


}
