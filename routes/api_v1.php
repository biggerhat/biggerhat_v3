<?php

use App\Http\Controllers\API\V1\AbilityController;
use App\Http\Controllers\API\V1\ActionController;
use App\Http\Controllers\API\V1\BlueprintController;
use App\Http\Controllers\API\V1\ChannelController;
use App\Http\Controllers\API\V1\CharacterController;
use App\Http\Controllers\API\V1\CharacteristicController;
use App\Http\Controllers\API\V1\FactionController;
use App\Http\Controllers\API\V1\KeywordController;
use App\Http\Controllers\API\V1\MarkerController;
use App\Http\Controllers\API\V1\MiniatureController;
use App\Http\Controllers\API\V1\PackageController;
use App\Http\Controllers\API\V1\SchemeController;
use App\Http\Controllers\API\V1\StrategyController;
use App\Http\Controllers\API\V1\TerrainController;
use App\Http\Controllers\API\V1\TokenController;
use App\Http\Controllers\API\V1\TransmissionController;
use App\Http\Controllers\API\V1\TriggerController;
use App\Http\Controllers\API\V1\UpgradeController;

Route::apiResource('characters', CharacterController::class)->only(['index', 'show']);
Route::apiResource('keywords', KeywordController::class)->only(['index', 'show']);
Route::apiResource('upgrades', UpgradeController::class)->only(['index', 'show']);
Route::apiResource('actions', ActionController::class)->only(['index', 'show']);
Route::apiResource('abilities', AbilityController::class)->only(['index', 'show']);
Route::apiResource('triggers', TriggerController::class)->only(['index', 'show']);
Route::apiResource('markers', MarkerController::class)->only(['index', 'show']);
Route::apiResource('tokens', TokenController::class)->only(['index', 'show']);
Route::apiResource('strategies', StrategyController::class)->only(['index', 'show']);
Route::apiResource('schemes', SchemeController::class)->only(['index', 'show']);
Route::apiResource('terrains', TerrainController::class)->only(['index', 'show']);
Route::apiResource('channels', ChannelController::class)->only(['index', 'show']);
Route::apiResource('transmissions', TransmissionController::class)->only(['index', 'show']);
Route::apiResource('packages', PackageController::class)->only(['index', 'show']);
Route::apiResource('blueprints', BlueprintController::class)->only(['index', 'show']);
Route::apiResource('characteristics', CharacteristicController::class)->only(['index', 'show']);
Route::apiResource('miniatures', MiniatureController::class)->only(['index', 'show']);

Route::get('factions', [FactionController::class, 'index'])->name('factions.index');
Route::get('factions/{faction}', [FactionController::class, 'show'])->name('factions.show');
