<?php

use App\Http\Controllers\ContractAttachmentFileController;
use App\Http\Controllers\ContractEditorController;
use App\Http\Controllers\ContractPdfController;
use App\Http\Controllers\ContractTemplateEditorController;
use App\Http\Controllers\OnlyOfficeContractController;
use App\Http\Controllers\OnlyOfficeOrderController;
use App\Http\Controllers\OnlyOfficeTemplateController;
use App\Http\Controllers\OrderEditorController;
use App\Http\Controllers\OrderFileController;
use App\Http\Controllers\TelegramConnectController;
use App\Http\Controllers\TelegramWebhookController;
use Illuminate\Support\Facades\Route;

Route::get('/contracts/{contract}/document', [OnlyOfficeContractController::class, 'document'])
    ->name('contracts.document');

Route::post('/contracts/{contract}/save-callback', [OnlyOfficeContractController::class, 'callback'])
    ->name('contracts.save-callback');

Route::get('/contract-templates/{template}/document', [OnlyOfficeTemplateController::class, 'document'])
    ->name('contract-templates.document');

Route::post('/contract-templates/{template}/save-callback', [OnlyOfficeTemplateController::class, 'callback'])
    ->name('contract-templates.save-callback');

Route::get('/orders/{order}/document', [OnlyOfficeOrderController::class, 'document'])
    ->name('orders.document');

Route::get('/contract-attachments/{attachment}/document', [ContractAttachmentFileController::class, 'document'])
    ->name('contract-attachments.document');

Route::post('/orders/{order}/save-callback', [OnlyOfficeOrderController::class, 'callback'])
    ->name('orders.save-callback');

Route::post('/telegram/webhook/{secret}', [TelegramWebhookController::class, 'handle'])
    ->middleware('throttle:120,1')
    ->name('telegram.webhook');

Route::middleware(['web', 'auth'])->group(function () {
    Route::get('/telegram/connect', [TelegramConnectController::class, 'connect'])
        ->name('telegram.connect');

    Route::get('/contracts/{contract}/editor', [ContractEditorController::class, 'show'])
        ->name('contracts.editor');

    Route::get('/contracts/{contract}/pdf', [ContractPdfController::class, 'download'])
        ->name('contracts.pdf.download');

    Route::get('/contracts/{contract}/pdf/inline', [ContractPdfController::class, 'inline'])
        ->name('contracts.pdf.inline');

    Route::get('/contract-templates/{template}/editor', [ContractTemplateEditorController::class, 'show'])
        ->name('contract-templates.editor');

    Route::get('/orders/{order}/editor', [OrderEditorController::class, 'show'])
        ->name('orders.editor');

    Route::get('/orders/{order}/file', [OrderFileController::class, 'inline'])
        ->name('orders.file.inline');

    Route::get('/contracts/{contract}/attachments/{attachment}/file', [ContractAttachmentFileController::class, 'inline'])
        ->name('contracts.attachments.inline');

    Route::get('/contracts/{contract}/attachments/{attachment}/viewer', [ContractAttachmentFileController::class, 'viewer'])
        ->name('contracts.attachments.viewer');
});
