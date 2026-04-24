<?php

use App\Http\Controllers\GameSystemController;
use App\Http\Controllers\TOS\Database\AbilityController;
use App\Http\Controllers\TOS\Database\ActionController;
use App\Http\Controllers\TOS\Database\AllegianceCardController;
use App\Http\Controllers\TOS\Database\AllegianceController;
use App\Http\Controllers\TOS\Database\AssetController;
use App\Http\Controllers\TOS\Database\EnvoyController;
use App\Http\Controllers\TOS\Database\SpecialUnitRuleController;
use App\Http\Controllers\TOS\Database\StratagemController;
use App\Http\Controllers\TOS\Database\TriggerController;
use App\Http\Controllers\TOS\Database\UnitController;
use App\Http\Controllers\TOS\HomeController;
use Illuminate\Support\Facades\Route;

Route::prefix('tos')->name('tos.')->group(function () {
    Route::get('/', [HomeController::class, 'index'])->name('index');

    Route::controller(AllegianceController::class)->prefix('allegiances')->name('allegiances.')->group(function () {
        Route::get('/', 'index')->name('index');
        Route::get('/{allegiance}', 'view')->name('view');
    });

    // Units. Per-type friendly URLs (commanders/titans/fireteams/squads/champions)
    // are aliases backed by one UnitController scoping by Special Unit Rule slug.
    Route::get('/units', [UnitController::class, 'index'])->name('units.index');
    Route::get('/units/{sculpt}', [UnitController::class, 'view'])->name('units.view');

    foreach (['commanders' => 'commander', 'titans' => 'titan', 'fireteams' => 'fireteam', 'squads' => 'squad', 'champions' => 'champion'] as $segment => $rule) {
        Route::get("/$segment", fn (\Illuminate\Http\Request $request) => app(UnitController::class)->index($request, $rule))->name("units.{$rule}");
    }

    Route::get('/special-rules', [SpecialUnitRuleController::class, 'index'])->name('special_rules.index');
    Route::get('/abilities', [AbilityController::class, 'index'])->name('abilities.index');
    Route::get('/actions', [ActionController::class, 'index'])->name('actions.index');
    Route::get('/triggers', [TriggerController::class, 'index'])->name('triggers.index');

    Route::controller(AllegianceCardController::class)->prefix('allegiance-cards')->name('allegiance_cards.')->group(function () {
        Route::get('/', 'index')->name('index');
        Route::get('/{card}', 'view')->name('view');
    });

    Route::controller(EnvoyController::class)->prefix('envoys')->name('envoys.')->group(function () {
        Route::get('/', 'index')->name('index');
        Route::get('/{envoy}', 'view')->name('view');
    });

    Route::controller(AssetController::class)->prefix('assets')->name('assets.')->group(function () {
        Route::get('/', 'index')->name('index');
        Route::get('/{asset}', 'view')->name('view');
    });

    Route::controller(StratagemController::class)->prefix('stratagems')->name('stratagems.')->group(function () {
        Route::get('/', 'index')->name('index');
        Route::get('/{stratagem}', 'view')->name('view');
    });
});

Route::post('/system/switch', [GameSystemController::class, 'switch'])->name('system.switch');
