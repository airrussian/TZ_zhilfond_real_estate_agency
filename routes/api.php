<?php

use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\NotificationReportController;
use Illuminate\Support\Facades\Route;

Route::prefix('notifications')->group(function () {
    Route::post('/', [NotificationController::class, 'store']);
    Route::get('/{notification}', [NotificationController::class, 'show']);
});

Route::get('/users/{user}/notifications', [NotificationController::class, 'userHistory']);

Route::post('/users/{user}/notification-reports', [NotificationReportController::class, 'store']);
Route::get('/users/{user}/notification-reports/{notification_report}', [NotificationReportController::class, 'show']);
Route::get('/users/{user}/notification-reports/{notification_report}/download', [NotificationReportController::class, 'download']);
