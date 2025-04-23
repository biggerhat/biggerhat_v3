<?php

use App\Http\Controllers\Admin\AbilityAdminController;
use App\Http\Controllers\Admin\ActionAdminController;
use App\Http\Controllers\Admin\CharacterAdminController;
use App\Http\Controllers\Admin\CharacteristicAdminController;
use App\Http\Controllers\Admin\KeywordAdminController;
use App\Http\Controllers\Admin\MarkerAdminController;
use App\Http\Controllers\Admin\MiniatureAdminController;
use App\Http\Controllers\Admin\SchemeAdminController;
use App\Http\Controllers\Admin\StrategyAdminController;
use App\Http\Controllers\Admin\TokenAdminController;
use App\Http\Controllers\Admin\TriggerAdminController;
use App\Http\Controllers\Admin\UpgradeAdminController;
use Illuminate\Support\Facades\Route;

Route::prefix('admin')->middleware(['auth', 'verified'])->middleware(['role:super_admin'])->name('admin.')->group(function () {
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

    Route::controller(ActionAdminController::class)->prefix('actions')->name('actions.')->group(function () {
        Route::get('/', 'index')->name('index');
        Route::get('/edit/{action}', 'edit')->name('edit');
        Route::get('/create', 'create')->name('create');
        Route::post('/store', 'store')->name('store');
        Route::post('/update/{action}', 'update')->name('update');
        Route::post('/delete/{action}', 'delete')->name('delete');
    });

    Route::controller(AbilityAdminController::class)->prefix('abilities')->name('abilities.')->group(function () {
        Route::get('/', 'index')->name('index');
        Route::get('/edit/{ability}', 'edit')->name('edit');
        Route::get('/create', 'create')->name('create');
        Route::post('/store', 'store')->name('store');
        Route::post('/update/{ability}', 'update')->name('update');
        Route::post('/delete/{ability}', 'delete')->name('delete');
    });

    Route::controller(TriggerAdminController::class)->prefix('triggers')->name('triggers.')->group(function () {
        Route::get('/', 'index')->name('index');
        Route::get('/edit/{trigger}', 'edit')->name('edit');
        Route::get('/create', 'create')->name('create');
        Route::post('/store', 'store')->name('store');
        Route::post('/update/{trigger}', 'update')->name('update');
        Route::post('/delete/{trigger}', 'delete')->name('delete');
    });

    Route::controller(MiniatureAdminController::class)->prefix('miniatures')->name('miniatures.')->group(function () {
        Route::get('/', 'index')->name('index');
        Route::get('/edit/{miniature}', 'edit')->name('edit');
        Route::get('/create', 'create')->name('create');
        Route::post('/store', 'store')->name('store');
        Route::post('/update/{miniature}', 'update')->name('update');
        Route::post('/delete/{miniature}', 'delete')->name('delete');
    });

    Route::controller(UpgradeAdminController::class)->prefix('upgrades')->name('upgrades.')->group(function () {
        Route::get('/', 'index')->name('index');
        Route::get('/edit/{upgrade}', 'edit')->name('edit');
        Route::get('/create', 'create')->name('create');
        Route::post('/store', 'store')->name('store');
        Route::post('/update/{upgrade}', 'update')->name('update');
        Route::post('/delete/{upgrade}', 'delete')->name('delete');
    });

    Route::controller(TokenAdminController::class)->prefix('tokens')->name('tokens.')->group(function () {
        Route::get('/', 'index')->name('index');
        Route::get('/edit/{token}', 'edit')->name('edit');
        Route::get('/create', 'create')->name('create');
        Route::post('/store', 'store')->name('store');
        Route::post('/update/{token}', 'update')->name('update');
        Route::post('/delete/{token}', 'delete')->name('delete');
    });

    Route::controller(MarkerAdminController::class)->prefix('markers')->name('markers.')->group(function () {
        Route::get('/', 'index')->name('index');
        Route::get('/edit/{marker}', 'edit')->name('edit');
        Route::get('/create', 'create')->name('create');
        Route::post('/store', 'store')->name('store');
        Route::post('/update/{marker}', 'update')->name('update');
        Route::post('/delete/{marker}', 'delete')->name('delete');
    });

    Route::controller(SchemeAdminController::class)->prefix('schemes')->name('schemes.')->group(function () {
        Route::get('/', 'index')->name('index');
        Route::get('/edit/{scheme}', 'edit')->name('edit');
        Route::get('/create', 'create')->name('create');
        Route::post('/store', 'store')->name('store');
        Route::post('/update/{scheme}', 'update')->name('update');
        Route::post('/delete/{scheme}', 'delete')->name('delete');
    });

    Route::controller(StrategyAdminController::class)->prefix('strategies')->name('strategies.')->group(function () {
        Route::get('/', 'index')->name('index');
        Route::get('/edit/{strategy}', 'edit')->name('edit');
        Route::get('/create', 'create')->name('create');
        Route::post('/store', 'store')->name('store');
        Route::post('/update/{strategy}', 'update')->name('update');
        Route::post('/delete/{strategy}', 'delete')->name('delete');
    });

});
