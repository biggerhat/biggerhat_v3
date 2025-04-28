<?php

use App\Http\Controllers\API\CharacterAPIController;
use App\Http\Controllers\API\MarkerAPIController;
use App\Http\Controllers\API\SchemeAPIController;
use App\Http\Controllers\API\StrategyAPIController;
use App\Http\Controllers\API\TokenAPIController;
use App\Http\Controllers\API\UpgradeAPIController;

Route::prefix('api')->name('api.')->group(function () {
    Route::get('/characters', [CharacterAPIController::class, 'view']);
    Route::get('/markers', [MarkerAPIController::class, 'view']);
    Route::get('/tokens', [TokenAPIController::class, 'view']);
    Route::get('/upgrades', [UpgradeAPIController::class, 'view']);
    Route::get('/schemes', [SchemeAPIController::class, 'view']);
    Route::get('/strategies', [StrategyAPIController::class, 'view']);
});
