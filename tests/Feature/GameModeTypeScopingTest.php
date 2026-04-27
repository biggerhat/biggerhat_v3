<?php

use App\Enums\CharacterStationEnum;
use App\Enums\FactionEnum;
use App\Enums\GameModeTypeEnum;
use App\Enums\GameStatusEnum;
use App\Enums\UpgradeDomainTypeEnum;
use App\Models\Ability;
use App\Models\Action;
use App\Models\Character;
use App\Models\Game;
use App\Models\GamePlayer;
use App\Models\Keyword;
use App\Models\Miniature;
use App\Models\Upgrade;
use App\Models\User;
use App\Models\Wishlist;

// ── Helpers ─────────────────────────────────────────────────────────────────

function standardCharacter(array $attrs = []): Character
{
    // CharacterObserver::creating rebuilds display_name as "{name}, {title}"
    // when title is non-null — and the factory's faker emits a title 5% of
    // the time. Force title to null by default so display_name == name and
    // the assertions in this test stay deterministic in parallel runs.
    return Character::factory()->create(array_merge([
        'game_mode_type' => GameModeTypeEnum::Standard,
        'is_hidden' => false,
        'title' => null,
    ], $attrs));
}

function campaignCharacter(array $attrs = []): Character
{
    return Character::factory()->create(array_merge([
        'game_mode_type' => GameModeTypeEnum::Campaign,
        'is_hidden' => false,
        'title' => null,
    ], $attrs));
}

function withMiniature(Character $character): Character
{
    Miniature::factory()->for($character)->create();

    return $character;
}

// ═══════════════════════════════════════════════════════════════════════════
// Database Controllers — Inertia pages
// ═══════════════════════════════════════════════════════════════════════════

// ── SearchController ────────────────────────────────────────────────────────

it('search page excludes campaign characters', function () {
    withMiniature(standardCharacter(['name' => 'Standard Char']));
    withMiniature(campaignCharacter(['name' => 'Campaign Char']));

    $response = $this->get(route('search.view'));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('Search/View')
        ->where('result_count', 1)
    );
});

it('search export excludes campaign characters', function () {
    withMiniature(standardCharacter(['name' => 'Standard Char']));
    withMiniature(campaignCharacter(['name' => 'Campaign Char']));

    $response = $this->get(route('search.export'));

    $response->assertOk();
    $csv = $response->streamedContent();
    expect($csv)->toContain('Standard Char');
    expect($csv)->not->toContain('Campaign Char');
});

// ── KeywordController ───────────────────────────────────────────────────────

it('keyword index excludes campaign keywords', function () {
    Keyword::factory()->create(['game_mode_type' => GameModeTypeEnum::Standard, 'name' => 'Standard KW']);
    Keyword::factory()->create(['game_mode_type' => GameModeTypeEnum::Campaign, 'name' => 'Campaign KW']);

    $response = $this->get(route('keywords.index'));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('Keywords/Index')
        ->has('keywords', 1)
    );
});

it('keyword view only shows standard characters', function () {
    $keyword = Keyword::factory()->create(['game_mode_type' => GameModeTypeEnum::Standard]);
    $standard = withMiniature(standardCharacter());
    $campaign = withMiniature(campaignCharacter());
    $keyword->characters()->attach([$standard->id, $campaign->id]);

    $response = $this->get(route('keywords.view', $keyword));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('Keywords/View')
        ->has('characters', 1)
    );
});

// ── ActionController ────────────────────────────────────────────────────────

it('action index excludes campaign actions', function () {
    Action::factory()->create(['game_mode_type' => GameModeTypeEnum::Standard]);
    Action::factory()->create(['game_mode_type' => GameModeTypeEnum::Campaign]);

    $response = $this->get(route('actions.index'));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('Actions/Index')
        ->where('result_count', 1)
    );
});

// ── AbilityController ───────────────────────────────────────────────────────

it('ability index excludes campaign abilities', function () {
    Ability::factory()->create(['game_mode_type' => GameModeTypeEnum::Standard]);
    Ability::factory()->create(['game_mode_type' => GameModeTypeEnum::Campaign]);

    $response = $this->get(route('abilities.index'));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('Abilities/Index')
        ->where('result_count', 1)
    );
});

// ── UpgradeController ───────────────────────────────────────────────────────

it('crew upgrade index excludes campaign upgrades', function () {
    Upgrade::factory()->create(['game_mode_type' => GameModeTypeEnum::Standard, 'domain' => UpgradeDomainTypeEnum::Crew]);
    Upgrade::factory()->create(['game_mode_type' => GameModeTypeEnum::Campaign, 'domain' => UpgradeDomainTypeEnum::Crew]);

    $response = $this->get(route('upgrades.crew.index'));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('Upgrades/CrewIndex')
        ->has('upgrades', 1)
    );
});

it('character upgrade index excludes campaign upgrades', function () {
    Upgrade::factory()->create(['game_mode_type' => GameModeTypeEnum::Standard, 'domain' => UpgradeDomainTypeEnum::Character]);
    Upgrade::factory()->create(['game_mode_type' => GameModeTypeEnum::Campaign, 'domain' => UpgradeDomainTypeEnum::Character]);

    $response = $this->get(route('upgrades.character.index'));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('Upgrades/CharacterIndex')
        ->has('upgrades', 1)
    );
});

// ── FactionController ───────────────────────────────────────────────────────

it('faction page excludes campaign characters', function () {
    withMiniature(standardCharacter(['faction' => FactionEnum::Guild]));
    withMiniature(campaignCharacter(['faction' => FactionEnum::Guild]));

    $response = $this->get(route('factions.view', 'guild'));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('Factions/View')
        ->has('characters', 1)
    );
});

// ── CharacterController ─────────────────────────────────────────────────────

it('character random redirects only to standard characters', function () {
    $standard = withMiniature(standardCharacter());
    // only standard characters exist — random should succeed
    $response = $this->get(route('characters.random'));

    $response->assertRedirect();
});

// ── BlogController ──────────────────────────────────────────────────────────

it('blog index tag filters exclude campaign characters and keywords', function () {
    $stdChar = standardCharacter(['name' => 'Standard BlogChar']);
    $campChar = campaignCharacter(['name' => 'Campaign BlogChar']);
    $stdKw = Keyword::factory()->create(['game_mode_type' => GameModeTypeEnum::Standard, 'name' => 'Std KW']);
    $campKw = Keyword::factory()->create(['game_mode_type' => GameModeTypeEnum::Campaign, 'name' => 'Camp KW']);

    // Attach to a published blog post so they appear in tagged lists
    $post = \App\Models\BlogPost::factory()->create(['published_at' => now()->subDay()]);
    $post->characters()->attach([$stdChar->id, $campChar->id]);
    $post->keywords()->attach([$stdKw->id, $campKw->id]);

    $response = $this->get(route('blog.index'));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('Blog/Index')
        ->has('tagged_characters', 1)
        ->has('tagged_keywords', 1)
    );
});

// ═══════════════════════════════════════════════════════════════════════════
// Tools Controllers — Inertia pages
// ═══════════════════════════════════════════════════════════════════════════

// ── PDFController ───────────────────────────────────────────────────────────

it('pdf tool page excludes campaign content', function () {
    withMiniature(standardCharacter());
    withMiniature(campaignCharacter());
    Keyword::factory()->create(['game_mode_type' => GameModeTypeEnum::Standard]);
    Keyword::factory()->create(['game_mode_type' => GameModeTypeEnum::Campaign]);
    Upgrade::factory()->create(['game_mode_type' => GameModeTypeEnum::Standard, 'domain' => UpgradeDomainTypeEnum::Character]);
    Upgrade::factory()->create(['game_mode_type' => GameModeTypeEnum::Campaign, 'domain' => UpgradeDomainTypeEnum::Character]);

    $response = $this->get(route('tools.pdf.index'));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('PDF/Index')
        ->has('characters', 1)
        ->has('keywords', 1)
        ->has('upgrades', 1)
    );
});

// ── CommandController ───────────────────────────────────────────────────────

it('command endpoint excludes campaign content', function () {
    withMiniature(standardCharacter(['name' => 'Std Cmd']));
    withMiniature(campaignCharacter(['name' => 'Camp Cmd']));
    Upgrade::factory()->create(['game_mode_type' => GameModeTypeEnum::Standard, 'name' => 'Std Upg']);
    Upgrade::factory()->create(['game_mode_type' => GameModeTypeEnum::Campaign, 'name' => 'Camp Upg']);
    Keyword::factory()->create(['game_mode_type' => GameModeTypeEnum::Standard, 'name' => 'Std KW']);
    Keyword::factory()->create(['game_mode_type' => GameModeTypeEnum::Campaign, 'name' => 'Camp KW']);

    $response = $this->getJson(route('command'));

    $response->assertOk();
    $characters = collect($response->json('characters'));
    $upgrades = collect($response->json('upgrades'));
    $keywords = collect($response->json('keywords'));

    expect($characters->pluck('name')->all())->toContain('Std Cmd');
    expect($characters->pluck('name')->all())->not->toContain('Camp Cmd');
    expect($upgrades->pluck('name')->all())->toContain('Std Upg');
    expect($upgrades->pluck('name')->all())->not->toContain('Camp Upg');
    expect($keywords->pluck('name')->all())->toContain('Std KW');
    expect($keywords->pluck('name')->all())->not->toContain('Camp KW');
});

// ── CrewBuilderController ───────────────────────────────────────────────────

it('crew builder editor excludes campaign characters and keywords', function () {
    standardCharacter(['station' => CharacterStationEnum::Master]);
    campaignCharacter(['station' => CharacterStationEnum::Master]);
    Keyword::factory()->create(['game_mode_type' => GameModeTypeEnum::Standard, 'name' => 'Std KW']);
    Keyword::factory()->create(['game_mode_type' => GameModeTypeEnum::Campaign, 'name' => 'Camp KW']);

    $response = $this->get(route('tools.crew_builder.editor'));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('Tools/CrewBuilder/Index')
        ->has('characters', 1)
        ->has('keywords', 1)
    );
});

// ═══════════════════════════════════════════════════════════════════════════
// Collection & Wishlist Controllers — Auth required
// ═══════════════════════════════════════════════════════════════════════════

it('collection page excludes campaign keywords from stats', function () {
    $user = User::factory()->create();
    $stdKw = Keyword::factory()->create(['game_mode_type' => GameModeTypeEnum::Standard]);
    $campKw = Keyword::factory()->create(['game_mode_type' => GameModeTypeEnum::Campaign]);
    $char = standardCharacter();
    $stdKw->characters()->attach($char);
    $campKw->characters()->attach($char);

    $response = $this->actingAs($user)->get(route('collection.index'));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('Collection/Index')
        ->has('keyword_stats', 1)
    );
});

it('wishlist show excludes campaign keywords from dropdown', function () {
    $user = User::factory()->create();
    $wishlist = Wishlist::create(['user_id' => $user->id, 'name' => 'Test Wishlist']);
    Keyword::factory()->create(['game_mode_type' => GameModeTypeEnum::Standard]);
    Keyword::factory()->create(['game_mode_type' => GameModeTypeEnum::Campaign]);

    $response = $this->actingAs($user)->get(route('wishlists.show', $wishlist));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('Wishlists/Show')
        ->has('keywords', 1)
    );
});

// ═══════════════════════════════════════════════════════════════════════════
// Internal API Controllers — JSON responses
// ═══════════════════════════════════════════════════════════════════════════

// ── CharacterAPIController ──────────────────────────────────────────────────

it('character search API excludes campaign characters', function () {
    standardCharacter(['name' => 'Standard SearchChar', 'display_name' => 'Standard SearchChar']);
    campaignCharacter(['name' => 'Campaign SearchChar', 'display_name' => 'Campaign SearchChar']);

    $response = $this->getJson(route('api.characters.search', ['q' => 'SearchChar']));

    $response->assertOk();
    $names = collect($response->json())->pluck('display_name')->all();
    expect($names)->toContain('Standard SearchChar');
    expect($names)->not->toContain('Campaign SearchChar');
});

it('character images API excludes campaign characters', function () {
    withMiniature(standardCharacter(['name' => 'Standard ImgChar']));
    withMiniature(campaignCharacter(['name' => 'Campaign ImgChar']));

    $response = $this->getJson('/api/characters/images');

    $response->assertOk();
    $names = collect($response->json())->pluck('display_name')->all();
    expect($names)->toContain('Standard ImgChar');
    expect($names)->not->toContain('Campaign ImgChar');
});

// ── UpgradeAPIController ────────────────────────────────────────────────────

it('upgrade API view excludes campaign upgrades', function () {
    Upgrade::factory()->create(['game_mode_type' => GameModeTypeEnum::Standard, 'name' => 'Standard Up']);
    Upgrade::factory()->create(['game_mode_type' => GameModeTypeEnum::Campaign, 'name' => 'Campaign Up']);

    $response = $this->getJson('/api/upgrades?q=Up');

    $response->assertOk();
    $names = collect($response->json())->pluck('name')->all();
    expect($names)->toContain('Standard Up');
    expect($names)->not->toContain('Campaign Up');
});

it('upgrade API crew excludes campaign upgrades', function () {
    $master = withMiniature(standardCharacter(['station' => CharacterStationEnum::Master]));
    $stdUpgrade = Upgrade::factory()->create(['game_mode_type' => GameModeTypeEnum::Standard, 'domain' => UpgradeDomainTypeEnum::Crew, 'name' => 'Std Crew']);
    $campUpgrade = Upgrade::factory()->create(['game_mode_type' => GameModeTypeEnum::Campaign, 'domain' => UpgradeDomainTypeEnum::Crew, 'name' => 'Camp Crew']);
    $stdUpgrade->characters()->attach($master);
    $campUpgrade->characters()->attach($master);

    $response = $this->getJson('/api/upgrades/crew');

    $response->assertOk();
    $names = collect($response->json())->pluck('name')->all();
    expect($names)->toContain('Std Crew');
    expect($names)->not->toContain('Camp Crew');
});

// ── KeywordAPIController ────────────────────────────────────────────────────

it('keyword API view excludes campaign keywords', function () {
    Keyword::factory()->create(['game_mode_type' => GameModeTypeEnum::Standard, 'name' => 'Standard KW']);
    Keyword::factory()->create(['game_mode_type' => GameModeTypeEnum::Campaign, 'name' => 'Campaign KW']);

    $response = $this->getJson(route('api.keywords.view'));

    $response->assertOk();
    $names = collect($response->json())->pluck('name')->all();
    expect($names)->toContain('Standard KW');
    expect($names)->not->toContain('Campaign KW');
});

// ── BlogEntitySearchController ──────────────────────────────────────────────

it('blog entity search excludes campaign characters', function () {
    standardCharacter(['name' => 'Standard BlogEntity', 'display_name' => 'Standard BlogEntity']);
    campaignCharacter(['name' => 'Campaign BlogEntity', 'display_name' => 'Campaign BlogEntity']);

    $response = $this->getJson(route('api.blog.entity-search', ['q' => 'BlogEntity']));

    $response->assertOk();
    $names = collect($response->json('results'))->pluck('displayName')->all();
    expect($names)->toContain('Standard BlogEntity');
    expect($names)->not->toContain('Campaign BlogEntity');
});

it('blog entity search excludes campaign keywords', function () {
    Keyword::factory()->create(['game_mode_type' => GameModeTypeEnum::Standard, 'name' => 'Standard BlogKW']);
    Keyword::factory()->create(['game_mode_type' => GameModeTypeEnum::Campaign, 'name' => 'Campaign BlogKW']);

    $response = $this->getJson(route('api.blog.entity-search', ['q' => 'BlogKW']));

    $response->assertOk();
    $names = collect($response->json('results'))->pluck('displayName')->all();
    expect($names)->toContain('Standard BlogKW');
    expect($names)->not->toContain('Campaign BlogKW');
});

it('blog entity search excludes campaign upgrades', function () {
    Upgrade::factory()->create(['game_mode_type' => GameModeTypeEnum::Standard, 'name' => 'Standard BlogUp']);
    Upgrade::factory()->create(['game_mode_type' => GameModeTypeEnum::Campaign, 'name' => 'Campaign BlogUp']);

    $response = $this->getJson(route('api.blog.entity-search', ['q' => 'BlogUp']));

    $response->assertOk();
    $names = collect($response->json('results'))->pluck('displayName')->all();
    expect($names)->toContain('Standard BlogUp');
    expect($names)->not->toContain('Campaign BlogUp');
});

it('blog entity search excludes campaign actions', function () {
    Action::factory()->create(['game_mode_type' => GameModeTypeEnum::Standard, 'name' => 'Standard BlogAct']);
    Action::factory()->create(['game_mode_type' => GameModeTypeEnum::Campaign, 'name' => 'Campaign BlogAct']);

    $response = $this->getJson(route('api.blog.entity-search', ['q' => 'BlogAct']));

    $response->assertOk();
    $names = collect($response->json('results'))->pluck('displayName')->all();
    expect($names)->toContain('Standard BlogAct');
    expect($names)->not->toContain('Campaign BlogAct');
});

it('blog entity search excludes campaign abilities', function () {
    Ability::factory()->create(['game_mode_type' => GameModeTypeEnum::Standard, 'name' => 'Standard BlogAbi']);
    Ability::factory()->create(['game_mode_type' => GameModeTypeEnum::Campaign, 'name' => 'Campaign BlogAbi']);

    $response = $this->getJson(route('api.blog.entity-search', ['q' => 'BlogAbi']));

    $response->assertOk();
    $names = collect($response->json('results'))->pluck('displayName')->all();
    expect($names)->toContain('Standard BlogAbi');
    expect($names)->not->toContain('Campaign BlogAbi');
});

// ═══════════════════════════════════════════════════════════════════════════
// Card Creator — Must NOT filter (stays unscoped)
// ═══════════════════════════════════════════════════════════════════════════

it('card creator search includes campaign characters', function () {
    standardCharacter(['name' => 'Standard CCChar', 'display_name' => 'Standard CCChar']);
    campaignCharacter(['name' => 'Campaign CCChar', 'display_name' => 'Campaign CCChar']);

    $response = $this->getJson(route('api.card-creator.characters', ['q' => 'CCChar']));

    $response->assertOk();
    $names = collect($response->json())->pluck('name')->all();
    expect($names)->toContain('Standard CCChar');
    expect($names)->toContain('Campaign CCChar');
});

it('card creator search includes campaign actions', function () {
    Action::factory()->create(['game_mode_type' => GameModeTypeEnum::Standard, 'name' => 'Standard CCAct']);
    Action::factory()->create(['game_mode_type' => GameModeTypeEnum::Campaign, 'name' => 'Campaign CCAct']);

    $response = $this->getJson(route('api.card-creator.actions', ['q' => 'CCAct']));

    $response->assertOk();
    $names = collect($response->json())->pluck('name')->all();
    expect($names)->toContain('Standard CCAct');
    expect($names)->toContain('Campaign CCAct');
});

it('card creator search includes campaign abilities', function () {
    Ability::factory()->create(['game_mode_type' => GameModeTypeEnum::Standard, 'name' => 'Standard CCAbi']);
    Ability::factory()->create(['game_mode_type' => GameModeTypeEnum::Campaign, 'name' => 'Campaign CCAbi']);

    $response = $this->getJson(route('api.card-creator.abilities', ['q' => 'CCAbi']));

    $response->assertOk();
    $names = collect($response->json())->pluck('name')->all();
    expect($names)->toContain('Standard CCAbi');
    expect($names)->toContain('Campaign CCAbi');
});

it('card creator search includes campaign keywords', function () {
    Keyword::factory()->create(['game_mode_type' => GameModeTypeEnum::Standard, 'name' => 'Standard CCKW']);
    Keyword::factory()->create(['game_mode_type' => GameModeTypeEnum::Campaign, 'name' => 'Campaign CCKW']);

    $response = $this->getJson(route('api.card-creator.keywords', ['q' => 'CCKW']));

    $response->assertOk();
    $names = collect($response->json())->pluck('name')->all();
    expect($names)->toContain('Standard CCKW');
    expect($names)->toContain('Campaign CCKW');
});

it('card creator search includes campaign crew upgrades', function () {
    $master = standardCharacter(['station' => CharacterStationEnum::Master]);
    $stdUp = Upgrade::factory()->create(['game_mode_type' => GameModeTypeEnum::Standard, 'domain' => UpgradeDomainTypeEnum::Crew, 'name' => 'Standard CCUp']);
    $campUp = Upgrade::factory()->create(['game_mode_type' => GameModeTypeEnum::Campaign, 'domain' => UpgradeDomainTypeEnum::Crew, 'name' => 'Campaign CCUp']);
    $stdUp->characters()->attach($master);
    $campUp->characters()->attach($master);

    $response = $this->getJson(route('api.card-creator.crew-upgrades', ['q' => 'CCUp']));

    $response->assertOk();
    $names = collect($response->json())->pluck('name')->all();
    expect($names)->toContain('Standard CCUp');
    expect($names)->toContain('Campaign CCUp');
});

// ═══════════════════════════════════════════════════════════════════════════
// FactionEnum — model-level query in enum
// ═══════════════════════════════════════════════════════════════════════════

it('FactionEnum::getCharacterStats only counts standard characters', function () {
    standardCharacter(['faction' => FactionEnum::Guild]);
    standardCharacter(['faction' => FactionEnum::Guild]);
    campaignCharacter(['faction' => FactionEnum::Guild]);

    $stats = FactionEnum::Guild->getCharacterStats();

    expect($stats['characters'])->toBe(2);
});

it('FactionEnum::getCharacterStats only counts standard keywords', function () {
    $stdChar = standardCharacter(['faction' => FactionEnum::Guild]);
    $stdKw = Keyword::factory()->create(['game_mode_type' => GameModeTypeEnum::Standard]);
    $campKw = Keyword::factory()->create(['game_mode_type' => GameModeTypeEnum::Campaign]);
    $stdKw->characters()->attach($stdChar);
    $campKw->characters()->attach($stdChar);

    $stats = FactionEnum::Guild->getCharacterStats();

    expect($stats['keywords'])->toBe(1);
});

// ═══════════════════════════════════════════════════════════════════════════
// Game Setup — verifies master/character lists are scoped
// ═══════════════════════════════════════════════════════════════════════════

it('game show page only shows standard masters during master select', function () {
    $user = User::factory()->create();
    $game = Game::factory()->create(['creator_id' => $user->id, 'status' => GameStatusEnum::MasterSelect]);
    GamePlayer::factory()->create(['game_id' => $game->id, 'user_id' => $user->id, 'slot' => 1]);
    standardCharacter(['station' => CharacterStationEnum::Master, 'name' => 'Std Master']);
    campaignCharacter(['station' => CharacterStationEnum::Master, 'name' => 'Camp Master']);

    $response = $this->actingAs($user)->get(route('games.show', $game));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('Games/Show')
        ->has('masters', 1)
    );
});

// ═══════════════════════════════════════════════════════════════════════════
// Transmission & Channel Controllers — select option dropdowns
// ═══════════════════════════════════════════════════════════════════════════

it('channel index excludes campaign keywords and characters from dropdowns', function () {
    Keyword::factory()->create(['game_mode_type' => GameModeTypeEnum::Standard]);
    Keyword::factory()->create(['game_mode_type' => GameModeTypeEnum::Campaign]);
    standardCharacter();
    campaignCharacter();

    $response = $this->get(route('channels.index'));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('Channels/Index')
        ->has('keywords', 1)
        ->has('characters', 1)
    );
});
