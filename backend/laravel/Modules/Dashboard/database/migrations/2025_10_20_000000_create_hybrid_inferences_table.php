<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hybrid_inferences', function (Blueprint $table) {
            $table->id();
            $table->string('iot_id');
            $table->string('image')->nullable();

            $table->string('pest_label')->nullable();
            $table->string('pest_class')->nullable();
            $table->float('pest_confidence')->nullable();

            $table->string('health_status')->nullable();
            $table->string('health_class')->nullable();
            $table->float('health_confidence')->nullable();

            $table->float('vpd_kpa')->nullable();
            $table->json('microclimate')->nullable();

            $table->float('fusion_w1')->nullable();
            $table->float('fusion_w2')->nullable();

            $table->string('actuation_action')->nullable();
            $table->json('actuators')->nullable();

            $table->string('transmitted_via')->nullable();
            $table->timestamps();

            $table->index(['iot_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hybrid_inferences');
    }
};
