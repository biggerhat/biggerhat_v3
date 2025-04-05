<?php

use App\Http\Controllers\Admin\FactionAdminController;
use Illuminate\Support\Facades\Route;

Route::prefix('admin')->middleware(['auth', 'verified'])->name('admin.')->group(function () {
    Route::controller(FactionAdminController::class)->prefix('factions')->name('factions.')->group(function () {
        Route::get('/', 'index')->name('index');
    });
});
