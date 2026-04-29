<?php

use App\Http\Controllers\GameSystemController;
use App\Http\Controllers\TOS\Database\AbilityController;
use App\Http\Controllers\TOS\Database\ActionController;
use App\Http\Controllers\TOS\Database\AllegianceCardController;
use App\Http\Controllers\TOS\Database\AllegianceController;
use App\Http\Controllers\TOS\Database\AssetController;
use App\Http\Controllers\TOS\Database\CompanyController;
use App\Http\Controllers\TOS\Database\CompareController;
use App\Http\Controllers\TOS\Database\PdfController as TosPdfController;
use App\Http\Controllers\TOS\Database\SearchController as TosSearchController;
use App\Http\Controllers\TOS\Database\SpecialUnitRuleController;
use App\Http\Controllers\TOS\Database\StratagemController;
use App\Http\Controllers\TOS\Database\TriggerController;
use App\Http\Controllers\TOS\Database\UnitController;
use App\Http\Controllers\TOS\HomeController;
use Illuminate\Support\Facades\Route;

Route::prefix('tos')->name('tos.')->group(function () {
    Route::get('/', [HomeController::class, 'index'])->name('index');

    Route::get('/compare', [CompareController::class, 'index'])->name('compare');

    Route::controller(TosSearchController::class)->prefix('search')->name('search')->group(function () {
        Route::get('/', 'view');
        Route::get('/export', 'export')->name('.export');
        Route::middleware('auth')->group(function () {
            Route::post('/save', 'saveSearch')->name('.save');
            Route::post('/saved/{savedSearch}/delete', 'deleteSavedSearch')->name('.saved.delete');
        });
    });

    Route::controller(AllegianceController::class)->prefix('allegiances')->name('allegiances.')->group(function () {
        Route::get('/', 'index')->name('index');
        // Type-pooled rosters MUST come before the catch-all `view` route so
        // they're not interpreted as an Allegiance slug binding.
        Route::get('/type/{type}', 'viewByType')->name('viewByType')->whereIn('type', ['earth', 'malifaux']);
        Route::get('/{allegiance}', 'view')->name('view');
    });

    // Units. Per-type friendly URLs (commanders/titans/fireteams/squads/champions)
    // are aliases backed by one UnitController scoping by Special Unit Rule slug.
    Route::get('/units', [UnitController::class, 'index'])->name('units.index');
    Route::get('/units/{sculpt}/pdf', [TosPdfController::class, 'download'])->name('units.pdf');
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

    Route::controller(AssetController::class)->prefix('assets')->name('assets.')->group(function () {
        Route::get('/', 'index')->name('index');
        Route::get('/{asset}', 'view')->name('view');
    });

    Route::controller(StratagemController::class)->prefix('stratagems')->name('stratagems.')->group(function () {
        Route::get('/', 'index')->name('index');
        Route::get('/{stratagem}', 'view')->name('view');
    });

    // Company Builder — auth-gated. Each Company belongs to one user; rule
    // enforcement (hireability, asset limits) lives in CompanyController.
    Route::middleware('auth')->controller(CompanyController::class)->prefix('companies')->name('companies.')->group(function () {
        Route::get('/', 'index')->name('index');
        Route::get('/create', 'create')->name('create');
        Route::post('/', 'store')->name('store');
        Route::get('/{company}', 'view')->name('view');
        Route::post('/{company}', 'update')->name('update');
        Route::post('/{company}/delete', 'delete')->name('delete');
        Route::post('/{company}/public', 'togglePublic')->name('toggle_public');
        Route::get('/{company}/pdf', 'downloadPdf')->name('pdf');
        Route::post('/{company}/units', 'addUnit')->name('units.add');
        Route::post('/{company}/units/{companyUnit}/delete', 'removeUnit')->name('units.remove');
        Route::post('/{company}/units/{companyUnit}/sculpt', 'updateSculpt')->name('units.sculpt');
        Route::post('/{company}/units/{companyUnit}/assets', 'attachAsset')->name('assets.attach');
        Route::post('/{company}/units/{companyUnit}/assets/{asset}/delete', 'detachAsset')->name('assets.detach');
    });

    // Public read-only Company view via share_code — no auth.
    Route::get('/c/{share_code}', [CompanyController::class, 'shared'])->name('companies.shared');
});

Route::post('/system/switch', [GameSystemController::class, 'switch'])->name('system.switch');
