<?php

/**
 * Add these routes to your routes/web.php or routes/backend.php file
 * in the appropriate middleware group (auth, admin, etc.)
 */

use App\Http\Controllers\Web\Backend\Core\NotificationCentreController;

Route::prefix('backend/notification-centre')->name('backend.notification-centre.')->group(function () {
    // Main page
    Route::get('/', [NotificationCentreController::class, 'index'])->name('index');
    
    // CRUD operations
    Route::post('/', [NotificationCentreController::class, 'store'])->name('store');
    Route::post('/{id}/update', [NotificationCentreController::class, 'update'])->name('update');
    Route::post('/{id}/schedule', [NotificationCentreController::class, 'schedule'])->name('schedule');
    
    // Actions
    Route::post('/{id}/send-now', [NotificationCentreController::class, 'sendNow'])->name('send-now');
    Route::post('/{id}/send-test', [NotificationCentreController::class, 'sendTest'])->name('send-test');
    Route::post('/{id}/cancel', [NotificationCentreController::class, 'cancel'])->name('cancel');
    Route::post('/{id}/pause', [NotificationCentreController::class, 'pause'])->name('pause');
    Route::post('/{id}/resume', [NotificationCentreController::class, 'resume'])->name('resume');
    Route::post('/{id}/toggle-testmode', [NotificationCentreController::class, 'toggleTestMode'])->name('toggle-testmode');
    
    // Brevo-specific test endpoints
    Route::post('/{id}/test-email', [NotificationCentreController::class, 'testEmail'])->name('test-email');
    Route::post('/{id}/test-sms', [NotificationCentreController::class, 'testSms'])->name('test-sms');
    
    // Data endpoints
    Route::get('/queue-data', [NotificationCentreController::class, 'queueData'])->name('queue-data');
    Route::get('/{id}/logs', [NotificationCentreController::class, 'logs'])->name('logs');
    
    // Bulk actions
    Route::post('/bulk', [NotificationCentreController::class, 'bulk'])->name('bulk');
    
    // Brevo templates
    Route::get('/templates', [NotificationCentreController::class, 'getTemplates'])->name('templates');
    
    // SMS character counter
    Route::post('/count-sms', [NotificationCentreController::class, 'countSmsCharacters'])->name('count-sms');
    
    // CSV upload
    Route::post('/upload-csv', [NotificationCentreController::class, 'uploadCsv'])->name('upload-csv');
});