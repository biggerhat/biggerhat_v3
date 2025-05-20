<?php

use App\Http\Controllers\CommandController;
use App\Http\Controllers\Database\CharacterController;
use App\Http\Controllers\Database\FactionController;
use App\Http\Controllers\Database\KeywordController;
use App\Http\Controllers\Database\UpgradeController;
use App\Models\Character;
use App\Models\Miniature;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Index');
})->name('index');

Route::get('/command', CommandController::class)->name('command');

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
    Route::get('/{keyword}', [KeywordController::class, 'view'])->name('view');
});

Route::prefix('factions')->name('factions.')->group(function () {
    Route::get('/{factionEnum}', [FactionController::class, 'view'])->name('view');
});

Route::prefix('upgrades')->name('upgrades.')->group(function () {
    Route::get('/{upgrade}', [UpgradeController::class, 'view'])->name('view');
});

require __DIR__.'/api.php';
require __DIR__.'/settings.php';
require __DIR__.'/auth.php';
require __DIR__.'/admin.php';
