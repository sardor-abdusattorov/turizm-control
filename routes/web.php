<?php

use App\Http\Controllers\ContractEditorController;
use App\Http\Controllers\OnlyOfficeContractController;
use Illuminate\Support\Facades\Route;

Route::get('/onlyoffice/{contract}/document', [OnlyOfficeContractController::class, 'document'])
    ->name('onlyoffice.contract.document');

Route::post('/onlyoffice/{contract}/callback', [OnlyOfficeContractController::class, 'callback'])
    ->name('onlyoffice.contract.callback');

Route::middleware(['web', 'auth'])->group(function () {
    Route::get('/contracts/{contract}/editor', [ContractEditorController::class, 'show'])
        ->name('contracts.editor');
});
