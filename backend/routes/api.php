<?php

use App\Http\Controllers\Api\SyncController;
use Illuminate\Support\Facades\Route;

Route::prefix('sync')->group(function () {
    Route::get('/pull', [SyncController::class, 'pull']);
    Route::post('/push', [SyncController::class, 'push']);
});
