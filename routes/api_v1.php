<?php

use App\Http\Controllers\API\V1\AbilityController;
use App\Http\Controllers\API\V1\ActionController;
use App\Http\Controllers\API\V1\BlueprintController;
use App\Http\Controllers\API\V1\ChannelController;
use App\Http\Controllers\API\V1\CharacterController;
use App\Http\Controllers\API\V1\CharacteristicController;
use App\Http\Controllers\API\V1\FactionController;
use App\Http\Controllers\API\V1\KeywordController;
use App\Http\Controllers\API\V1\LoreController;
use App\Http\Controllers\API\V1\LoreMediaController;
use App\Http\Controllers\API\V1\MarkerController;
use App\Http\Controllers\API\V1\MiniatureController;
use App\Http\Controllers\API\V1\PackageController;
use App\Http\Controllers\API\V1\SchemeController;
use App\Http\Controllers\API\V1\StrategyController;
use App\Http\Controllers\API\V1\TerrainController;
use App\Http\Controllers\API\V1\TokenController;
use App\Http\Controllers\API\V1\TOS\AbilityController as TosAbilityController;
use App\Http\Controllers\API\V1\TOS\ActionController as TosActionController;
use App\Http\Controllers\API\V1\TOS\AllegianceCardController as TosAllegianceCardController;
use App\Http\Controllers\API\V1\TOS\AllegianceController as TosAllegianceController;
use App\Http\Controllers\API\V1\TOS\AssetController as TosAssetController;
use App\Http\Controllers\API\V1\TOS\EnvoyController as TosEnvoyController;
use App\Http\Controllers\API\V1\TOS\SpecialUnitRuleController as TosSpecialUnitRuleController;
use App\Http\Controllers\API\V1\TOS\StratagemController as TosStratagemController;
use App\Http\Controllers\API\V1\TOS\TriggerController as TosTriggerController;
use App\Http\Controllers\API\V1\TOS\UnitController as TosUnitController;
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

Route::get('crews', [\App\Http\Controllers\API\V1\CrewBuildController::class, 'index'])->name('crews.index');
Route::get('crews/{shareCode}', [\App\Http\Controllers\API\V1\CrewBuildController::class, 'show'])->name('crews.show');

Route::get('games', [\App\Http\Controllers\API\V1\GameController::class, 'index'])->name('games.index');
Route::get('games/{game:uuid}', [\App\Http\Controllers\API\V1\GameController::class, 'show'])->name('games.show');

// Lore (public Malifaux fiction/articles + the source media they belong to). Lore + LoreMedia
// don't override `getRouteKeyName()` on the model, so bind by slug here for clean public URLs.
Route::get('lore', [LoreController::class, 'index'])->name('lore.index');
Route::get('lore/{lore:slug}', [LoreController::class, 'show'])->name('lore.show');
Route::get('lore-media', [LoreMediaController::class, 'index'])->name('lore_media.index');
Route::get('lore-media/{loreMedium:slug}', [LoreMediaController::class, 'show'])->name('lore_media.show');

// The Other Side (TOS) — companion game system. Mirrors the Malifaux V1 surface for parity.
Route::prefix('tos')->name('tos.')->group(function () {
    Route::apiResource('allegiances', TosAllegianceController::class)->only(['index', 'show']);
    Route::apiResource('allegiance-cards', TosAllegianceCardController::class)->only(['index', 'show'])->parameter('allegiance-cards', 'allegianceCard');
    Route::apiResource('units', TosUnitController::class)->only(['index', 'show']);
    Route::apiResource('assets', TosAssetController::class)->only(['index', 'show']);
    Route::apiResource('envoys', TosEnvoyController::class)->only(['index', 'show']);
    Route::apiResource('stratagems', TosStratagemController::class)->only(['index', 'show']);
    // SpecialUnitRule's URL parameter would default to {special_unit_rule}; we use the simpler {rule} path.
    Route::get('special-unit-rules', [TosSpecialUnitRuleController::class, 'index'])->name('special_unit_rules.index');
    Route::get('special-unit-rules/{specialUnitRule:slug}', [TosSpecialUnitRuleController::class, 'show'])->name('special_unit_rules.show');
    Route::apiResource('abilities', TosAbilityController::class)->only(['index', 'show']);
    Route::apiResource('actions', TosActionController::class)->only(['index', 'show']);
    // Trigger model doesn't override getRouteKeyName but the table has unique slug — bind by slug.
    Route::get('triggers', [TosTriggerController::class, 'index'])->name('triggers.index');
    Route::get('triggers/{trigger:slug}', [TosTriggerController::class, 'show'])->name('triggers.show');
});
