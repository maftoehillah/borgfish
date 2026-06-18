<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\GoogleAuthController;
use App\Http\Controllers\Auth\OnboardingController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\Auth\WhatsappOtpController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('register', [RegisteredUserController::class, 'create'])
        ->name('register');

    Route::post('register', [RegisteredUserController::class, 'store']);

    Route::get('login', [AuthenticatedSessionController::class, 'create'])
        ->name('login');

    Route::post('login', [AuthenticatedSessionController::class, 'store']);

    Route::get('auth/google/redirect', [GoogleAuthController::class, 'redirect'])
        ->name('auth.google.redirect');

    Route::get('auth/google/callback', [GoogleAuthController::class, 'callback'])
        ->name('auth.google.callback');
});

Route::middleware(['auth', 'user.active'])->group(function () {
    Route::get('auth/onboarding', [OnboardingController::class, 'show'])
        ->name('auth.onboarding.show');
    Route::post('auth/onboarding', [OnboardingController::class, 'store'])
        ->name('auth.onboarding.store');

    Route::get('auth/otp', [WhatsappOtpController::class, 'show'])
        ->name('auth.otp.challenge');
    Route::post('auth/otp', [WhatsappOtpController::class, 'verify'])
        ->middleware('throttle:otp-verify')
        ->name('auth.otp.verify');
    Route::post('auth/otp/resend', [WhatsappOtpController::class, 'resend'])
        ->middleware('throttle:otp-resend')
        ->name('auth.otp.resend');

    Route::post('logout', [AuthenticatedSessionController::class, 'destroy'])
        ->name('logout');
});
