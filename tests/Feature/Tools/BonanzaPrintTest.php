<?php

use App\Models\Action;
use App\Models\LootCard;
use App\Models\Trigger;
use App\Services\BonanzaDeckPdfGenerator;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\View;

it('serves the cached print PDF without re-rendering', function () {
    Storage::fake('public');
    // Pre-seed the cache (at the template-hashed path) so the controller
    // streams it instead of invoking Chrome.
    Storage::disk('public')->put(app(BonanzaDeckPdfGenerator::class)->cachePath(), '%PDF-1.4 fake');

    $resp = $this->get(route('tools.bonanza_loot_deck.print'));

    $resp->assertOk();
    expect($resp->headers->get('content-type'))->toContain('application/pdf');
    expect($resp->getContent())->toStartWith('%PDF');
});

it('sends no-cache headers on the print route — the cache path is versioned by template, not card content', function () {
    Storage::fake('public');
    Storage::disk('public')->put(app(BonanzaDeckPdfGenerator::class)->cachePath(), '%PDF-1.4 fake');

    $resp = $this->get(route('tools.bonanza_loot_deck.print'));

    $resp->assertOk();
    expect($resp->headers->get('cache-control'))->toContain('no-store');
});

it('BonanzaDeck Blade renders {{+}} and {{-}} as font glyphs, not literal braces', function () {
    $card = LootCard::create([
        'slug' => 'twist-test', 'name' => 'Twist Test', 'suit' => 'crow', 'value' => 2, 'value_label' => '2', 'sort_order' => 1,
        'effect_a' => 'Gains a {{+}} to duels. Suffers a {{-}} to defense.',
    ]);
    $card->load([
        'sideAActions.triggers', 'sideBActions.triggers',
        'sideAAbilities', 'sideBAbilities',
        'sideATriggers', 'sideBTriggers',
    ]);

    $html = View::make('PDF.BonanzaDeck', ['cards' => collect([$card])])->render();

    expect($html)->not->toContain('{{+}}');
    expect($html)->not->toContain('{{-}}');
    expect($html)->toContain('<span class="gi">+</span>');
    expect($html)->toContain('<span class="gi">-</span>');
});

it('BonanzaDeck Blade renders a soulstone glyph for a stone-cost trigger, both standalone and action-nested', function () {
    $standaloneTrigger = Trigger::factory()->create(['name' => 'Standalone Trig', 'stone_cost' => 1, 'suits' => null]);
    $nestedTrigger = Trigger::factory()->create(['name' => 'Nested Trig', 'stone_cost' => 2, 'suits' => null]);
    $action = Action::factory()->create(['name' => 'Host Action']);
    $action->triggers()->attach($nestedTrigger->id);

    $card = LootCard::create([
        'slug' => 'stone-test', 'name' => 'Stone Test', 'suit' => 'crow', 'value' => 1, 'value_label' => '1', 'sort_order' => 1,
    ]);
    $card->syncSideTriggers('a', [$standaloneTrigger->id]);
    $card->syncSideActions('a', [['action_id' => $action->id, 'is_signature_action' => false]]);

    $card->load([
        'sideAActions.triggers', 'sideBActions.triggers',
        'sideAAbilities', 'sideBAbilities',
        'sideATriggers', 'sideBTriggers',
    ]);

    $html = View::make('PDF.BonanzaDeck', ['cards' => collect([$card])])->render();

    // Two soulstone glyphs for the nested trigger's stone_cost of 2, one for
    // the standalone trigger's stone_cost of 1 — plus each trigger's name,
    // right next to the glyphs (not floating elsewhere in the page).
    expect($html)->toContain('Standalone Trig');
    expect($html)->toContain('Nested Trig');
    $standaloneChunk = substr($html, (int) strpos($html, 'Standalone Trig') - 60, 60);
    $nestedChunk = substr($html, (int) strpos($html, 'Nested Trig') - 80, 80);
    expect(substr_count($standaloneChunk, '<span class="gi">s</span>'))->toBe(1);
    expect(substr_count($nestedChunk, '<span class="gi">s</span>'))->toBe(2);
});

it('BonanzaDeck Blade renders the signature action glyph for a signature action, and omits it for a non-signature one', function () {
    // stone_cost fixed at 0 — a random Faker-generated stone_cost would
    // prepend a variable number of soulstone glyphs before the name, which
    // could push the signature glyph outside the lookbehind window below.
    $signatureAction = Action::factory()->create(['name' => 'Signature Move', 'stone_cost' => 0]);
    $plainAction = Action::factory()->create(['name' => 'Plain Move', 'stone_cost' => 0]);

    $card = LootCard::create([
        'slug' => 'signature-test', 'name' => 'Signature Test', 'suit' => 'crow', 'value' => 3, 'value_label' => '3', 'sort_order' => 1,
    ]);
    $card->syncSideActions('a', [
        ['action_id' => $signatureAction->id, 'is_signature_action' => true],
        ['action_id' => $plainAction->id, 'is_signature_action' => false],
    ]);

    $card->load([
        'sideAActions.triggers', 'sideBActions.triggers',
        'sideAAbilities', 'sideBAbilities',
        'sideATriggers', 'sideBTriggers',
    ]);

    $html = View::make('PDF.BonanzaDeck', ['cards' => collect([$card])])->render();

    $signatureChunk = substr($html, (int) strpos($html, 'Signature Move') - 60, 60);
    $plainChunk = substr($html, (int) strpos($html, 'Plain Move') - 60, 60);
    expect($signatureChunk)->toContain('<span class="gi">f</span>');
    expect($plainChunk)->not->toContain('<span class="gi">f</span>');
});
