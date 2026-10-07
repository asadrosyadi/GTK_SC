<?php

use Illuminate\Support\Facades\Route;
use Modules\Dashboard\Http\Controllers\DashboardController;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/datasensor', [DashboardController::class, 'getData']);
    Route::get('/datayolo', [DashboardController::class, 'getDataKamera']);
    Route::get('/getRecommendations', [DashboardController::class, 'getDataPrediksi']);
    Route::get('/ai/summary', [DashboardController::class, 'aiSummary'])->name('ai.summary');
    Route::post('/ai/chat', [DashboardController::class, 'aiChat'])->middleware('throttle:ai-chat')->name('ai.chat');
});
