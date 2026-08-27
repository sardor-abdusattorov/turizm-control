<?php

use App\Http\Controllers\ContractAttachmentFileController;
use App\Http\Controllers\ContractDocumentController;
use App\Http\Controllers\OrderFileController;
use App\Http\Controllers\TelegramConnectController;
use App\Http\Controllers\TelegramWebhookController;
use Illuminate\Support\Facades\Route;

Route::post('/telegram/webhook/{secret}', [TelegramWebhookController::class, 'handle'])
    ->middleware('throttle:120,1')
    ->name('telegram.webhook');

Route::middleware(['web', 'auth'])->group(function () {
    Route::get('/telegram/connect', [TelegramConnectController::class, 'connect'])
        ->name('telegram.connect');

    Route::get('/contracts/{contract}/document/download', [ContractDocumentController::class, 'download'])
        ->name('contracts.document.download');

    Route::get('/orders/{order}/file', [OrderFileController::class, 'inline'])
        ->name('orders.file.inline');

    Route::get('/contracts/{contract}/attachments/{attachment}/file', [ContractAttachmentFileController::class, 'inline'])
        ->name('contracts.attachments.inline');
});
