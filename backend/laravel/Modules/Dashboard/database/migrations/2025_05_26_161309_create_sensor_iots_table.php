<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sensor_iots', function (Blueprint $table) {
            $table->id();
            $table->text('iot_id');
            $table->decimal('temperature', 5, 2);
            $table->decimal('humidity', 5, 2);
            $table->decimal('windspeed', 5, 2);
            $table->decimal('rainfall', 5, 2);
            $table->integer('light_intensity');
            $table->decimal('ph',  5, 1);
            $table->decimal('soil_moisture', 5, 2);
            $table->decimal('ec', 5, 2);
            $table->decimal('tds', 5, 2);
            $table->decimal('soil_temp', 5, 2);
            $table->decimal('pressure', 5, 2);
            $table->integer('feromon');
            $table->integer('battery_level');
            $table->integer('signal_strength');
            $table->decimal('Nitrogen_Level', 5, 2);
            $table->decimal('Phosphorus_Level', 5, 2);
            $table->decimal('Potassium_Level', 5, 2);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sensor_iots');
    }
};
