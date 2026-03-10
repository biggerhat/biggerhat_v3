<?php

use App\Enums\FactionEnum;
use App\Http\Controllers\CollectionController;
use App\Http\Controllers\CommandController;
use App\Http\Controllers\CrewBuilderController;
use App\Http\Controllers\Database\AbilityController;
use App\Http\Controllers\Database\ActionController;
use App\Http\Controllers\Database\BlogController;
use App\Http\Controllers\Database\CharacterController;
use App\Http\Controllers\Database\FactionController;
use App\Http\Controllers\Database\KeywordController;
use App\Http\Controllers\Database\LoreController;
use App\Http\Controllers\Database\MarkerController;
use App\Http\Controllers\Database\PackageController;
use App\Http\Controllers\Database\SchemeController;
use App\Http\Controllers\Database\SearchController;
use App\Http\Controllers\Database\SeasonController;
use App\Http\Controllers\Database\StrategyController;
use App\Http\Controllers\Database\TokenController;
use App\Http\Controllers\Database\UpgradeController;
use App\Http\Controllers\HatGaminController;
use App\Http\Controllers\PDFController;
use App\Http\Controllers\ScenarioGeneratorController;
use App\Models\Character;
use App\Models\Keyword;
use App\Models\Miniature;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    $featured = Character::with('standardMiniatures')->inRandomOrder()->first();

    return Inertia::render('Index', [
        'factions' => FactionEnum::buildDetails(),
        'featured_character' => $featured,
        'stats' => [
            'characters' => Character::count(),
            'keywords' => Keyword::count(),
        ],
    ]);
})->name('index');

Route::get('/command', CommandController::class)->name('command');

Route::get('/advanced', [SearchController::class, 'view'])->name('search.view');

Route::prefix('characters')->name('characters.')->group(function () {
    Route::get('/{character}/{miniature:id}/{slug}', [CharacterController::class, 'view'])->name('view');
    Route::get('/random', [CharacterController::class, 'random'])->name('random');
    Route::get('/{character}', function (Character $character) {
        $character->loadMissing('standardMiniatures');

        return \Route::dispatch(\Request::create(
            route('characters.view', ['character' => $character, 'miniature' => $character['standardMiniatures'][0]['id'], 'slug' => $character['standardMiniatures'][0]['slug']])
        ));
    });
    Route::get('/{character}/{miniature:id}/', function (Character $character, Miniature $miniature) {
        return \Route::dispatch(\Request::create(
            route('characters.view', ['character' => $character, 'miniature' => $miniature->id, 'slug' => $miniature->slug])
        ));
    });
});

Route::prefix('keywords')->name('keywords.')->group(function () {
    Route::get('/', [KeywordController::class, 'index'])->name('index');
    Route::get('/{keyword}', [KeywordController::class, 'view'])->name('view');
});

Route::prefix('markers')->name('markers.')->group(function () {
    Route::get('/', [MarkerController::class, 'index'])->name('index');
});

Route::prefix('tokens')->name('tokens.')->group(function () {
    Route::get('/', [TokenController::class, 'index'])->name('index');
});

Route::prefix('actions')->name('actions.')->group(function () {
    Route::get('/', [ActionController::class, 'index'])->name('index');
});

Route::prefix('abilities')->name('abilities.')->group(function () {
    Route::get('/', [AbilityController::class, 'index'])->name('index');
});

Route::prefix('factions')->name('factions.')->group(function () {
    Route::get('/{factionEnum}', [FactionController::class, 'view'])->name('view');
});

Route::prefix('upgrades')->name('upgrades.')->group(function () {
    Route::get('/crew', [UpgradeController::class, 'crewIndex'])->name('crew.index');
    Route::get('/character', [UpgradeController::class, 'characterIndex'])->name('character.index');
    Route::get('/{upgrade}', [UpgradeController::class, 'view'])->name('view');
});

Route::prefix('packages')->name('packages.')->group(function () {
    Route::get('/', [PackageController::class, 'index'])->name('index');
    Route::get('/{package}', [PackageController::class, 'view'])->name('view');
});

Route::prefix('lore')->name('lores.')->group(function () {
    Route::get('/', [LoreController::class, 'index'])->name('index');
});

Route::prefix('blog')->name('blog.')->group(function () {
    Route::get('/', [BlogController::class, 'index'])->name('index');
    Route::get('/{blogPost}', [BlogController::class, 'view'])->name('view');
});

Route::prefix('seasons')->name('seasons.')->group(function () {
    Route::get('/', [SeasonController::class, 'index'])->name('index');
    Route::get('/{season}', [SeasonController::class, 'view'])->name('view');
});

Route::prefix('schemes')->name('schemes.')->group(function () {
    Route::get('/{scheme}', [SchemeController::class, 'view'])->name('view');
});

Route::prefix('strategies')->name('strategies.')->group(function () {
    Route::get('/{strategy}', [StrategyController::class, 'view'])->name('view');
});

Route::prefix('tools')->name('tools.')->group(function () {
    Route::get('/hat_gamin', HatGaminController::class)->name('hat_gamin');
    Route::prefix('pdf')->name('pdf.')->group(function () {
        Route::get('/', [PDFController::class, 'index'])->name('index');
        Route::get('/download', [PDFController::class, 'download'])->name('download');
    });
    Route::get('/scenario-generator', [ScenarioGeneratorController::class, 'index'])->name('scenario_generator');
    Route::prefix('crew-builder')->name('crew_builder.')->group(function () {
        Route::get('/', [CrewBuilderController::class, 'index'])->name('index');
        Route::get('/share/{shareCode}', [CrewBuilderController::class, 'share'])->name('share');
        Route::post('/', [CrewBuilderController::class, 'store'])->name('store')->middleware('auth');
        Route::put('/{crewBuild}', [CrewBuilderController::class, 'update'])->name('update')->middleware('auth');
        Route::delete('/{crewBuild}', [CrewBuilderController::class, 'destroy'])->name('destroy')->middleware('auth');
    });
});

Route::prefix('collection')->name('collection.')->group(function () {
    Route::get('/share/{shareCode}', [CollectionController::class, 'share'])->name('share');

    Route::middleware('auth')->group(function () {
        Route::get('/', [CollectionController::class, 'index'])->name('index');
        Route::post('/toggle', [CollectionController::class, 'toggle'])->name('toggle');
        Route::post('/toggle-public', [CollectionController::class, 'togglePublic'])->name('toggle_public');
        Route::post('/add-character', [CollectionController::class, 'addCharacter'])->name('add_character');
        Route::post('/add-package', [CollectionController::class, 'addPackage'])->name('add_package');
        Route::post('/toggle-package', [CollectionController::class, 'togglePackage'])->name('toggle_package');
        Route::post('/remove', [CollectionController::class, 'remove'])->name('remove');
    });
});

require __DIR__.'/api.php';
require __DIR__.'/settings.php';
require __DIR__.'/auth.php';
require __DIR__.'/admin.php';
