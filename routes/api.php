<?php

use App\Http\Controllers\PembayaranController;
use Illuminate\Support\Facades\Route;

Route::post('/tripay/callback', [PembayaranController::class, 'webhook'])
    ->middleware('throttle:payment-webhook')
    ->name('tripay.callback');
