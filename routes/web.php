<?php

use App\Http\Controllers\Database\CharacterController;
use App\Http\Controllers\Database\FactionController;
use App\Http\Controllers\Database\KeywordController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Index');
})->name('index');

Route::prefix('characters')->name('characters.')->group(function () {
    Route::get('/{character}/{miniature:id}/{slug}', [CharacterController::class, 'view'])->name('view');
});

Route::prefix('keywords')->name('keywords.')->group(function () {
    Route::get('/{keyword}', [KeywordController::class, 'view'])->name('view');
});

Route::prefix('factions')->name('factions.')->group(function () {
    Route::get('/{factionEnum}', [FactionController::class, 'view'])->name('view');
});

require __DIR__.'/api.php';
require __DIR__.'/settings.php';
require __DIR__.'/auth.php';
require __DIR__.'/admin.php';
