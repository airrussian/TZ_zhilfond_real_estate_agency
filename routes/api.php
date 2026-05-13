<?php

use App\Http\Controllers\Api\NotificationController;
use Illuminate\Support\Facades\Route;

Route::prefix('notifications')->group(function () {
    Route::post('/', [NotificationController::class, 'store']);
    Route::get('/{notification}', [NotificationController::class, 'show']);
});

Route::get('/users/{user}/notifications', [NotificationController::class, 'userHistory']);
