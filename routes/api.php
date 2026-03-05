<?php

use App\Http\Controllers\API\BlogEntitySearchController;
use App\Http\Controllers\API\CharacterAPIController;
use App\Http\Controllers\API\KeywordAPIController;
use App\Http\Controllers\API\MarkerAPIController;
use App\Http\Controllers\API\SchemeAPIController;
use App\Http\Controllers\API\StrategyAPIController;
use App\Http\Controllers\API\TokenAPIController;
use App\Http\Controllers\API\UpgradeAPIController;

Route::prefix('api')->name('api.')->group(function () {
    Route::get('/characters', [CharacterAPIController::class, 'view']);
    Route::get('/characters/images', [CharacterAPIController::class, 'images']);
    Route::get('/markers', [MarkerAPIController::class, 'view']);
    Route::get('/tokens', [TokenAPIController::class, 'view']);
    Route::get('/upgrades/crew', [UpgradeAPIController::class, 'crew']);
    Route::get('/upgrades', [UpgradeAPIController::class, 'view']);
    Route::get('/schemes', [SchemeAPIController::class, 'view']);
    Route::get('/strategies', [StrategyAPIController::class, 'view']);
    Route::get('/keywords', [KeywordAPIController::class, 'view'])->name('keywords.view');

    Route::get('/blog/entity-search', [BlogEntitySearchController::class, 'search'])->name('blog.entity-search');
    Route::get('/blog/entity/{type}/{slug}', [BlogEntitySearchController::class, 'show'])->name('blog.entity-show');
});
