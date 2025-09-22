<?php

use Illuminate\Support\Facades\Route;
use Rayzenai\LaravelSms\Http\Controllers\SendSmsController;
use Rayzenai\LaravelSms\Http\Controllers\SendBulkSmsController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your package. These
| routes are loaded by the LaravelSmsServiceProvider.
|
*/

Route::prefix('api')->group(function () {
    // Send single SMS
    Route::post('/sms/send', SendSmsController::class)->name('sms.send');
    
    // Send bulk SMS
    Route::post('/sms/send-bulk', SendBulkSmsController::class)->name('sms.send-bulk');
});
