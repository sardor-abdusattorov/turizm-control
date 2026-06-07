<?php

use App\Http\Controllers\ContractEditorController;
use App\Http\Controllers\ContractPdfController;
use App\Http\Controllers\ContractTemplateEditorController;
use App\Http\Controllers\OnlyOfficeContractController;
use App\Http\Controllers\OnlyOfficeTemplateController;
use Illuminate\Support\Facades\Route;

Route::get('/onlyoffice/{contract}/document', [OnlyOfficeContractController::class, 'document'])
    ->name('onlyoffice.contract.document');

Route::post('/onlyoffice/{contract}/callback', [OnlyOfficeContractController::class, 'callback'])
    ->name('onlyoffice.contract.callback');

Route::get('/onlyoffice/template/{template}/document', [OnlyOfficeTemplateController::class, 'document'])
    ->name('onlyoffice.template.document');

Route::post('/onlyoffice/template/{template}/callback', [OnlyOfficeTemplateController::class, 'callback'])
    ->name('onlyoffice.template.callback');

Route::middleware(['web', 'auth'])->group(function () {
    Route::get('/contracts/{contract}/editor', [ContractEditorController::class, 'show'])
        ->name('contracts.editor');

    Route::get('/contracts/{contract}/pdf', [ContractPdfController::class, 'download'])
        ->name('contracts.pdf.download');

    Route::get('/contracts/{contract}/pdf/inline', [ContractPdfController::class, 'inline'])
        ->name('contracts.pdf.inline');

    Route::get('/contract-templates/{template}/editor', [ContractTemplateEditorController::class, 'show'])
        ->name('contract-templates.editor');
});
