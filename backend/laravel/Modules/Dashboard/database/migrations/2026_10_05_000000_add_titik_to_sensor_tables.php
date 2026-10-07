<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private array $decimalColumns = [
        'temperature', 'humidity', 'windspeed', 'rainfall', 'ph', 'soil_moisture', 'ec', 'tds',
        'soil_temp', 'pressure', 'Nitrogen_Level', 'Phosphorus_Level', 'Potassium_Level',
    ];

    private array $integerColumns = ['light_intensity', 'feromon', 'battery_level', 'signal_strength'];

    public function up(): void
    {
        Schema::table('sensor_iots', function (Blueprint $table) {
            foreach ($this->decimalColumns as $column) {
                $table->decimal($column, 10, 2)->nullable()->change();
            }
            foreach ($this->integerColumns as $column) {
                $table->integer($column)->nullable()->change();
            }
            if (! Schema::hasColumn('sensor_iots', 'titik')) {
                $table->unsignedTinyInteger('titik')->default(1)->after('iot_id');
            }
        });

        Schema::table('sensor_kameras', function (Blueprint $table) {
            if (! Schema::hasColumn('sensor_kameras', 'titik')) {
                $table->unsignedTinyInteger('titik')->default(1)->after('iot_id');
            }
            if (! Schema::hasColumn('sensor_kameras', 'updated_at')) {
                $table->timestamp('updated_at')->nullable();
            }
        });

        Schema::table('hybrid_inferences', function (Blueprint $table) {
            if (! Schema::hasColumn('hybrid_inferences', 'titik')) {
                $table->unsignedTinyInteger('titik')->default(1)->after('iot_id');
            }
        });
    }

    public function down(): void
    {
        foreach (['sensor_iots', 'sensor_kameras', 'hybrid_inferences'] as $tableName) {
            if (Schema::hasColumn($tableName, 'titik')) {
                Schema::table($tableName, function (Blueprint $table) {
                    $table->dropColumn('titik');
                });
            }
        }
    }
};
