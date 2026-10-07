<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['sensor_iots', 'sensor_kameras'] as $table) {
            if (! Schema::hasColumn($table, 'is_dummy')) {
                Schema::table($table, function (Blueprint $t) {
                    $t->boolean('is_dummy')->default(false)->index();
                });
            }
        }
    }

    public function down(): void
    {
        foreach (['sensor_iots', 'sensor_kameras'] as $table) {
            if (Schema::hasColumn($table, 'is_dummy')) {
                Schema::table($table, function (Blueprint $t) use ($table) {
                    $t->dropIndex($table . '_is_dummy_index');
                    $t->dropColumn('is_dummy');
                });
            }
        }
    }
};
