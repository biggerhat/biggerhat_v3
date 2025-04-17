<?php

use App\Http\Controllers\Admin\CharacterAdminController;
use App\Http\Controllers\Admin\CharacteristicAdminController;
use App\Http\Controllers\Admin\KeywordAdminController;
use Illuminate\Support\Facades\Route;

Route::prefix('admin')->middleware(['auth', 'verified'])->name('admin.')->group(function () {
    Route::controller(KeywordAdminController::class)->prefix('keywords')->name('keywords.')->group(function () {
        Route::get('/', 'index')->name('index');
        Route::get('/edit/{keyword}', 'edit')->name('edit');
        Route::get('/create', 'create')->name('create');
        Route::post('/store', 'store')->name('store');
        Route::post('/update/{keyword}', 'update')->name('update');
        Route::post('/delete/{keyword}', 'delete')->name('delete');
    });

    Route::controller(CharacteristicAdminController::class)->prefix('characteristics')->name('characteristics.')->group(function () {
        Route::get('/', 'index')->name('index');
        Route::get('/edit/{characteristic}', 'edit')->name('edit');
        Route::get('/create', 'create')->name('create');
        Route::post('/store', 'store')->name('store');
        Route::post('/update/{characteristic}', 'update')->name('update');
        Route::post('/delete/{characteristic}', 'delete')->name('delete');
    });

    Route::controller(CharacterAdminController::class)->prefix('characters')->name('characters.')->group(function () {
        Route::get('/', 'index')->name('index');
        Route::get('/edit/{character}', 'edit')->name('edit');
        Route::get('/create', 'create')->name('create');
        Route::post('/store', 'store')->name('store');
        Route::post('/update/{character}', 'update')->name('update');
        Route::post('/delete/{character}', 'delete')->name('delete');
    });
});
