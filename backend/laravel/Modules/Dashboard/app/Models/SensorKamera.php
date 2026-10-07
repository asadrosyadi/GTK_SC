<?php

namespace Modules\Dashboard\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class SensorKamera extends Model
{
    use HasFactory;

    protected $table = 'sensor_kameras';
    protected $fillable = ['iot_id', 'titik', 'penyakit', 'probabilitas', 'image', 'is_dummy'];

    protected $casts = ['is_dummy' => 'boolean'];

}
