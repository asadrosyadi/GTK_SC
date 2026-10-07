<?php

use Illuminate\Support\Facades\Route;
use Modules\History\Http\Controllers\HistoryController;


Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/export-data', [HistoryController::class, 'exportData'])->name('export.data');
});

