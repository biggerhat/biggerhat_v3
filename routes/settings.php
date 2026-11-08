<?php

use App\Http\Controllers\Settings\DeviceTokenController;
use App\Http\Controllers\Settings\OverviewController;
use App\Http\Controllers\Settings\PasswordController;
use App\Http\Controllers\Settings\ProfileController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::middleware('auth')->group(function () {
    // My Hub is the logged-in home; /settings now just leads to the account pages.
    Route::get('hub', [OverviewController::class, 'index'])->name('overview');
    Route::redirect('settings', '/settings/profile');

    Route::get('settings/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('settings/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('settings/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::get('settings/password', [PasswordController::class, 'edit'])->name('password.edit');
    Route::put('settings/password', [PasswordController::class, 'update'])->name('password.update');

    // Native app push tokens (JSON, called from the Capacitor shell).
    Route::post('settings/device-tokens', [DeviceTokenController::class, 'store'])->name('device_tokens.store');
    Route::delete('settings/device-tokens', [DeviceTokenController::class, 'destroy'])->name('device_tokens.destroy');

    Route::get('settings/appearance', function () {
        return Inertia::render('Settings/Appearance');
    })->name('appearance');
});
