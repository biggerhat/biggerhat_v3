<?php

use App\Http\Controllers\API\BlogEntitySearchController;
use App\Http\Controllers\API\BlogPostAPIController;
use App\Http\Controllers\API\CardCreatorSearchController;
use App\Http\Controllers\API\CharacterAPIController;
use App\Http\Controllers\API\KeywordAPIController;
use App\Http\Controllers\API\MarkerAPIController;
use App\Http\Controllers\API\SchemeAPIController;
use App\Http\Controllers\API\StrategyAPIController;
use App\Http\Controllers\API\TokenAPIController;
use App\Http\Controllers\API\UpgradeAPIController;

Route::prefix('api')->name('api.')->group(function () {
    Route::get('/characters', [CharacterAPIController::class, 'view'])->name('characters.view');
    Route::get('/characters/search', [CharacterAPIController::class, 'search'])->name('characters.search');
    Route::get('/characters/compare', [CharacterAPIController::class, 'compare'])->name('characters.compare');
    Route::get('/characters/{character}/miniatures', [CharacterAPIController::class, 'miniatures'])->name('characters.miniatures');
    Route::get('/characters/images', [CharacterAPIController::class, 'images']);
    Route::get('/markers', [MarkerAPIController::class, 'view']);
    Route::get('/tokens', [TokenAPIController::class, 'view']);
    Route::get('/upgrades/crew', [UpgradeAPIController::class, 'crew']);
    Route::get('/upgrades', [UpgradeAPIController::class, 'view']);
    Route::get('/schemes', [SchemeAPIController::class, 'view']);
    Route::get('/strategies', [StrategyAPIController::class, 'view']);
    Route::get('/keywords', [KeywordAPIController::class, 'view'])->name('keywords.view');

    Route::get('/card-creator/actions', [CardCreatorSearchController::class, 'actions'])->name('card-creator.actions');
    Route::get('/card-creator/abilities', [CardCreatorSearchController::class, 'abilities'])->name('card-creator.abilities');
    Route::get('/card-creator/triggers', [CardCreatorSearchController::class, 'triggers'])->name('card-creator.triggers');
    Route::get('/card-creator/keywords', [CardCreatorSearchController::class, 'keywords'])->name('card-creator.keywords');
    Route::get('/card-creator/crew-upgrades', [CardCreatorSearchController::class, 'crewUpgrades'])->name('card-creator.crew-upgrades');
    Route::get('/card-creator/characters', [CardCreatorSearchController::class, 'characters'])->name('card-creator.characters');
    Route::get('/card-creator/tokens', [CardCreatorSearchController::class, 'tokens'])->name('card-creator.tokens');
    Route::get('/card-creator/markers', [CardCreatorSearchController::class, 'markers'])->name('card-creator.markers');
    Route::get('/card-creator/character-detail/{character:id}', [CardCreatorSearchController::class, 'characterDetail'])->name('card-creator.character-detail');

    Route::get('/blog/posts', [BlogPostAPIController::class, 'index'])->name('blog.posts.index');

    Route::get('/blog/entity-search', [BlogEntitySearchController::class, 'search'])->name('blog.entity-search');
    Route::get('/blog/entity/{type}/{slug}', [BlogEntitySearchController::class, 'show'])->name('blog.entity-show');
});
