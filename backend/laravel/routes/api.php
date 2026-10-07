<?php

use App\Http\Controllers\CameraUploadController;
use App\Http\Controllers\SensorController;
use Modules\Dashboard\Http\Controllers\HybridInferenceController;
use Illuminate\Support\Facades\Route;

Route::post('/kirim_sensor', [SensorController::class, 'store']);
Route::post('/kirim_gambar', [CameraUploadController::class, 'store'])->middleware('throttle:device-images');
Route::get('/sensor', [SensorController::class, 'getByiot_id']);

Route::post('/inference', [HybridInferenceController::class, 'store']);
Route::get('/inference/{iot_id}', [HybridInferenceController::class, 'latest']);

Route::prefix('v1')->name('api.v1.')->middleware(\App\Http\Middleware\ApiLocale::class)->group(function () {
    Route::post('auth/login', [\App\Http\Controllers\Api\V1\AuthController::class, 'login'])
        ->middleware('throttle:api-login')->name('auth.login');
    Route::post('auth/forgot-password', [\App\Http\Controllers\Api\V1\AuthController::class, 'forgotPassword'])
        ->middleware('throttle:api-forgot')->name('auth.forgot-password');

    Route::post('device/sensor-readings', [SensorController::class, 'store'])->name('device.sensor-readings.store');
    Route::post('device/images', [CameraUploadController::class, 'store'])
        ->middleware('throttle:device-images')->name('device.images.store');

    Route::middleware(['auth:sanctum', 'throttle:api', \App\Http\Middleware\ApiLocale::class])->group(function () {
        Route::get('auth/me', [\App\Http\Controllers\Api\V1\AuthController::class, 'me'])->name('auth.me');
        Route::post('auth/logout', [\App\Http\Controllers\Api\V1\AuthController::class, 'logout'])->name('auth.logout');
        Route::match(['post', 'patch'], 'auth/profile', [\App\Http\Controllers\Api\V1\AuthController::class, 'updateProfile'])->name('auth.profile');
        Route::put('auth/password', [\App\Http\Controllers\Api\V1\AuthController::class, 'updatePassword'])
            ->middleware('throttle:api-password')->name('auth.password');
        Route::get('devices', [\App\Http\Controllers\Api\V1\DeviceController::class, 'index'])->name('devices.index');

        Route::get('plots', [\App\Http\Controllers\Api\V1\PlotController::class, 'index'])->name('plots.index');
        Route::get('plots/{titik}', [\App\Http\Controllers\Api\V1\PlotController::class, 'show'])->whereNumber('titik')->name('plots.show');
        Route::get('plots/{titik}/forecast', [\App\Http\Controllers\Api\V1\PlotController::class, 'forecast'])->whereNumber('titik')->name('plots.forecast');
        Route::get('recommendations', [\App\Http\Controllers\Api\V1\PlotController::class, 'recommendations'])->name('recommendations.index');
        Route::get('snapshot', [\App\Http\Controllers\Api\V1\PlotController::class, 'snapshot'])->name('snapshot');

        Route::get('dashboard', \App\Http\Controllers\Api\V1\DashboardController::class)->name('dashboard');
        Route::get('forecast', [\App\Http\Controllers\Api\V1\ForecastController::class, 'forecast'])->name('forecast');
        Route::get('history', [\App\Http\Controllers\Api\V1\ForecastController::class, 'measured'])->name('history');
        Route::get('history/export', [\App\Http\Controllers\Api\V1\ForecastController::class, 'export'])
            ->middleware('throttle:api-export')->name('history.export');
        Route::get('detections/summary', [\App\Http\Controllers\Api\V1\DetectionController::class, 'summary'])->name('detections.summary');
        Route::get('detections/export', [\App\Http\Controllers\Api\V1\DeviceController::class, 'exportDetections'])
            ->middleware('throttle:api-export')->name('detections.export');
        Route::get('ai/summary', [\App\Http\Controllers\Api\V1\AiController::class, 'summary'])->name('ai.summary');
        Route::post('ai/chat', [\App\Http\Controllers\Api\V1\AiController::class, 'chat'])
            ->middleware('throttle:ai-chat')->name('ai.chat');

        foreach ([
            'sensor-readings' => \App\Http\Controllers\Api\V1\SensorReadingController::class,
            'detections' => \App\Http\Controllers\Api\V1\DetectionController::class,
            'inferences' => \App\Http\Controllers\Api\V1\InferenceController::class,
        ] as $uri => $controller) {
            Route::get($uri, [$controller, 'index'])->name("{$uri}.index");
            Route::get("{$uri}/latest", [$controller, 'latest'])->name("{$uri}.latest");
            Route::get("{$uri}/{id}", [$controller, 'show'])->whereNumber('id')->name("{$uri}.show");
        }
    });
});
