<?php

use App\Http\Controllers\OnlyOfficeTestController;
use Illuminate\Support\Facades\Route;

Route::get('/onlyoffice-test/document', [OnlyOfficeTestController::class, 'document'])
    ->name('onlyoffice-test.document');

Route::post('/onlyoffice-test/callback', [OnlyOfficeTestController::class, 'callback'])
    ->name('onlyoffice-test.callback');
