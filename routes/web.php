<?php

use App\Http\Controllers\ChannelController;
use App\Http\Controllers\CollectionController;
use App\Http\Controllers\CommandController;
use App\Http\Controllers\CrewBuilderController;
use App\Http\Controllers\Database\AbilityController;
use App\Http\Controllers\Database\ActionController;
use App\Http\Controllers\Database\BlogController;
use App\Http\Controllers\Database\BlueprintController;
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
use App\Http\Controllers\Database\TriggerController;
use App\Http\Controllers\Database\UpgradeController;
use App\Http\Controllers\GameController;
use App\Http\Controllers\GamePlayController;
use App\Http\Controllers\GameSetupController;
use App\Http\Controllers\HatGaminController;
use App\Http\Controllers\PDFController;
use App\Http\Controllers\ScenarioGeneratorController;
use App\Http\Controllers\TransmissionController;
use App\Http\Controllers\WishlistController;
use App\Models\BlogPost;
use App\Models\Character;
use App\Models\CrewBuild;
use App\Models\Keyword;
use App\Models\Miniature;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Broadcast::routes(['middleware' => ['web', 'auth']]);

Route::get('/', function () {
    return Inertia::render('Index', [
        'featured_character' => fn () => Character::with(['standardMiniatures' => fn ($q) => $q->limit(1)])
            ->whereHas('standardMiniatures')
            ->inRandomOrder()
            ->first(),
        'recent_crews' => fn () => CrewBuild::where('is_public', true)
            ->with('user:id,name', 'master:id,name,title,display_name,slug')
            ->latest()
            ->take(6)
            ->get()
            ->map(fn (CrewBuild $build) => [
                'id' => $build->id,
                'name' => $build->name,
                'faction' => $build->faction->value,
                'faction_label' => $build->faction->label(),
                'faction_logo' => $build->faction->logo(),
                'master_name' => $build->master?->display_name,
                'encounter_size' => $build->encounter_size,
                'share_code' => $build->share_code,
                'user_name' => $build->user?->name,
                'created_at' => $build->created_at->diffForHumans(),
            ]),
        'recent_articles' => fn () => BlogPost::published()
            ->with('category:id,name')
            ->latest('published_at')
            ->take(3)
            ->get()
            ->map(fn (BlogPost $post) => [
                'title' => $post->title,
                'slug' => $post->slug,
                'category' => $post->category?->name,
                'published_at' => $post->published_at->diffForHumans(),
            ]),
        'recent_transmissions' => fn () => \App\Models\Transmission::with('channel:id,name,slug,image')
            ->latest('release_date')
            ->take(4)
            ->get()
            ->map(fn (\App\Models\Transmission $t) => [
                'id' => $t->id,
                'title' => $t->title,
                'description' => $t->description,
                'release_date' => $t->release_date->diffForHumans(),
                'channel_name' => $t->channel?->name,
                'channel_slug' => $t->channel?->slug,
                'channel_image' => $t->channel?->image,
            ]),
        'stats' => fn () => [
            'characters' => Character::count(),
            'keywords' => Keyword::count(),
            'miniatures' => Miniature::count(),
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

Route::prefix('triggers')->name('triggers.')->group(function () {
    Route::get('/', [TriggerController::class, 'index'])->name('index');
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

Route::prefix('blueprints')->name('blueprints.')->group(function () {
    Route::get('/', [BlueprintController::class, 'index'])->name('index');
});

Route::prefix('lore')->name('lores.')->group(function () {
    Route::get('/', [LoreController::class, 'index'])->name('index');
});

Route::prefix('blog')->name('blog.')->group(function () {
    Route::get('/', [BlogController::class, 'index'])->name('index');
    Route::get('/{blogPost}', [BlogController::class, 'view'])->name('view');
});

Route::prefix('channels')->name('channels.')->group(function () {
    Route::get('/', [ChannelController::class, 'index'])->name('index');
    Route::get('/my-channels', [ChannelController::class, 'myChannels'])->middleware('auth')->name('my');
    Route::get('/{channel}', [ChannelController::class, 'view'])->name('view');
});

Route::middleware('auth')->prefix('channels/{channel}/transmissions')->name('transmissions.')->group(function () {
    Route::get('/create', [TransmissionController::class, 'create'])->name('create');
    Route::post('/store', [TransmissionController::class, 'store'])->name('store');
    Route::get('/edit/{transmission}', [TransmissionController::class, 'edit'])->name('edit');
    Route::post('/update/{transmission}', [TransmissionController::class, 'update'])->name('update');
    Route::post('/delete/{transmission}', [TransmissionController::class, 'delete'])->name('delete');
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
        Route::get('/', [CrewBuilderController::class, 'browse'])->name('index');
        Route::get('/editor', [CrewBuilderController::class, 'editor'])->name('editor');
        Route::get('/references', [CrewBuilderController::class, 'references'])->name('references');
        Route::get('/share/{shareCode}', [CrewBuilderController::class, 'share'])->name('share');
        Route::get('/{crewBuild}/details', [CrewBuilderController::class, 'details'])->name('details')->middleware('auth');
        Route::post('/', [CrewBuilderController::class, 'store'])->name('store')->middleware('auth');
        Route::put('/{crewBuild}', [CrewBuilderController::class, 'update'])->name('update')->middleware('auth');
        Route::delete('/{crewBuild}', [CrewBuilderController::class, 'destroy'])->name('destroy')->middleware('auth');
    });
});

Route::prefix('wishlists')->name('wishlists.')->group(function () {
    Route::get('/share/{shareCode}', [WishlistController::class, 'share'])->name('share');

    Route::middleware('auth')->group(function () {
        Route::get('/', [WishlistController::class, 'index'])->name('index');
        Route::post('/', [WishlistController::class, 'store'])->name('store');
        Route::get('/{wishlist}', [WishlistController::class, 'show'])->name('show');
        Route::put('/{wishlist}', [WishlistController::class, 'update'])->name('update');
        Route::delete('/{wishlist}', [WishlistController::class, 'destroy'])->name('destroy');
        Route::post('/{wishlist}/items', [WishlistController::class, 'addItem'])->name('items.add');
        Route::delete('/{wishlist}/items/{wishlistItem}', [WishlistController::class, 'removeItem'])->name('items.remove');
        Route::post('/{wishlist}/add-keyword', [WishlistController::class, 'addKeyword'])->name('add_keyword');
        Route::post('/{wishlist}/toggle-public', [WishlistController::class, 'togglePublic'])->name('toggle_public');
    });
});

// Public game observation (no auth required)
Route::get('/games/{game:uuid}/observe', [GameController::class, 'observe'])->name('games.observe');

Route::prefix('games')->name('games.')->middleware('auth')->group(function () {
    Route::get('/', [GameController::class, 'index'])->name('index');
    Route::get('/create', [GameController::class, 'create'])->name('create');
    Route::post('/', [GameController::class, 'store'])->name('store');
    Route::get('/{game:uuid}/join', [GameController::class, 'join'])->name('join');
    Route::get('/{game:uuid}', [GameController::class, 'show'])->name('show');
    Route::put('/{game:uuid}/scenario', [GameController::class, 'updateScenario'])->name('scenario.update');
    Route::post('/{game:uuid}/regenerate', [GameController::class, 'regenerateScenario'])->name('scenario.regenerate');
    Route::delete('/{game:uuid}', [GameController::class, 'destroy'])->name('destroy');
    Route::post('/{game:uuid}/abandon', [GameController::class, 'abandon'])->name('abandon');
    Route::post('/{game:uuid}/toggle-observable', [GameController::class, 'toggleObservable'])->name('toggle_observable');

    // Setup steps
    Route::prefix('/{game:uuid}/setup')->name('setup.')->group(function () {
        Route::post('/faction', [GameSetupController::class, 'submitFaction'])->name('faction');
        Route::post('/master', [GameSetupController::class, 'submitMaster'])->name('master');
        Route::post('/crew', [GameSetupController::class, 'submitCrew'])->name('crew');
        Route::post('/crew/skip', [GameSetupController::class, 'skipCrew'])->name('crew.skip');
        Route::post('/scheme', [GameSetupController::class, 'submitScheme'])->name('scheme');
        Route::post('/swap-roles', [GameSetupController::class, 'swapRoles'])->name('swap_roles');
        Route::post('/opponent-name', [GameSetupController::class, 'updateOpponentName'])->name('opponent_name');
    });

    // Gameplay
    Route::prefix('/{game:uuid}/play')->name('play.')->group(function () {
        Route::patch('/crew/{gameCrewMember}', [GamePlayController::class, 'updateCrewMember'])->name('crew.update');
        Route::post('/crew/{gameCrewMember}/kill', [GamePlayController::class, 'killCrewMember'])->name('crew.kill');
        Route::post('/crew/{gameCrewMember}/revive', [GamePlayController::class, 'reviveCrewMember'])->name('crew.revive');
        Route::post('/crew/summon', [GamePlayController::class, 'summonCrewMember'])->name('crew.summon');
        Route::patch('/soulstones', [GamePlayController::class, 'updateSoulstonePool'])->name('soulstones');
        Route::post('/turns', [GamePlayController::class, 'submitTurnScore'])->name('turns.store');
        Route::post('/complete', [GamePlayController::class, 'markComplete'])->name('complete');
    });
});

Route::prefix('collection')->name('collection.')->group(function () {
    Route::get('/share/{shareCode}', [CollectionController::class, 'share'])->name('share');

    Route::middleware('auth')->group(function () {
        Route::get('/', [CollectionController::class, 'index'])->name('index');
        Route::post('/toggle', [CollectionController::class, 'toggle'])->name('toggle');
        Route::post('/toggle-public', [CollectionController::class, 'togglePublic'])->name('toggle_public');
        Route::post('/add-character', [CollectionController::class, 'addCharacter'])->name('add_character');
        Route::post('/add-characters', [CollectionController::class, 'addCharacters'])->name('add_characters');
        Route::post('/add-package', [CollectionController::class, 'addPackage'])->name('add_package');
        Route::post('/toggle-package', [CollectionController::class, 'togglePackage'])->name('toggle_package');
        Route::post('/update-status', [CollectionController::class, 'updateStatus'])->name('update_status');
        Route::post('/remove', [CollectionController::class, 'remove'])->name('remove');
    });
});

require __DIR__.'/api.php';
require __DIR__.'/settings.php';
require __DIR__.'/auth.php';
require __DIR__.'/admin.php';
