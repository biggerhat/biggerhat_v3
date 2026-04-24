<?php

namespace Database\Seeders\TOS;

use App\Enums\TOS\ActionTypeEnum;
use App\Enums\TOS\SpecialUnitRuleEnum;
use App\Enums\TOS\UnitSideEnum;
use App\Models\TOS\Ability;
use App\Models\TOS\Action;
use App\Models\TOS\Allegiance;
use App\Models\TOS\SpecialUnitRule;
use App\Models\TOS\Unit;
use App\Models\TOS\UnitSculpt;
use App\Models\TOS\UnitSide;
use Illuminate\Database\Seeder;

/**
 * Lands at least one rulebook-faithful exemplar per Special Unit Rule type, so
 * the database browser is usable as a demo straight after a fresh seed.
 *
 * Source: TOS rulebook (2018 launch). The Royal Rifle Corps (King's Empire,
 * page 12) is shown explicitly as a 3-model fireteam unit.
 */
class UnitSeeder extends Seeder
{
    public function run(): void
    {
        $kingsEmpire = Allegiance::where('slug', 'kings_empire')->first();
        $abyssinia = Allegiance::where('slug', 'abyssinia')->first();
        $cult = Allegiance::where('slug', 'cult_of_the_burning_man')->first();

        if (! $kingsEmpire || ! $abyssinia || ! $cult) {
            $this->command?->warn('TOS allegiances missing — run AllegianceSeeder first.');

            return;
        }

        // Commander exemplar — Earl Burns of the King's Empire.
        $earl = $this->createUnit(
            slug: 'earl-burns',
            name: 'Earl Burns',
            title: 'King\'s Hand',
            scrip: 8,            // Commander provides scrip; magnitude stored positive.
            tactics: '2',
            description: 'Veteran officer commanding from the front lines.',
            allegiance: $kingsEmpire,
            standardAvs: ['speed' => 5, 'defense' => 5, 'willpower' => 6, 'armor' => 2],
            gloryAvs: ['speed' => 5, 'defense' => 6, 'willpower' => 7, 'armor' => 3],
            specialRules: [SpecialUnitRuleEnum::Commander, SpecialUnitRuleEnum::Unique],
        );
        $this->attachAction($earl, ActionTypeEnum::Morale, 'Fire on My Command', 0, null, '6"', null,
            'Target friendly Fireteam immediately performs an Action.');

        // Fireteam + Squad exemplar — Royal Rifle Corps (rulebook p. 12 reference).
        $rifle = $this->createUnit(
            slug: 'royal-rifle-corps',
            name: 'Royal Rifle Corps',
            title: null,
            scrip: 4,
            tactics: '1',
            description: 'Disciplined infantry fireteam armed with rifled muskets.',
            allegiance: $kingsEmpire,
            standardAvs: ['speed' => 5, 'defense' => 4, 'willpower' => 4, 'armor' => 1],
            gloryAvs: ['speed' => 5, 'defense' => 5, 'willpower' => 5, 'armor' => 2],
            specialRules: [
                [SpecialUnitRuleEnum::Fireteam, ['base_mm' => 30, 'models_per_team' => 3, 'model_size_mm' => 30]],
                [SpecialUnitRuleEnum::Squad, ['fireteam_count' => 2]],
            ],
        );
        $this->attachAction($rifle, ActionTypeEnum::Missile, 'Hail of Bullets', 4, 'Df', '6"', 1,
            'Hail of Bullets — sustained rifle fire.');

        // Titan exemplar — King's Hand (rulebook example, p. 23).
        $titan = $this->createUnit(
            slug: 'kings-hand',
            name: 'King\'s Hand',
            title: null,
            scrip: 12,
            tactics: null,
            description: 'Towering steam-driven titan that crushes battlefields.',
            allegiance: $kingsEmpire,
            standardAvs: ['speed' => 5, 'defense' => 4, 'willpower' => 5, 'armor' => 4],
            gloryAvs: ['speed' => 6, 'defense' => 5, 'willpower' => 6, 'armor' => 5],
            specialRules: [SpecialUnitRuleEnum::Titan, SpecialUnitRuleEnum::Unique],
        );
        $this->attachAction($titan, ActionTypeEnum::Melee, 'Crushing Fist', 6, 'Df', 'y', 3,
            'Devastating melee attack.');

        // Champion exemplar — Abyssinian field officer.
        $abyChamp = $this->createUnit(
            slug: 'iron-vanguard',
            name: 'Iron Vanguard',
            title: null,
            scrip: 6,
            tactics: '1',
            description: 'Abyssinian veteran trained to absorb hits for nearby squads.',
            allegiance: $abyssinia,
            standardAvs: ['speed' => 5, 'defense' => 5, 'willpower' => 5, 'armor' => 3],
            gloryAvs: ['speed' => 5, 'defense' => 6, 'willpower' => 6, 'armor' => 4],
            specialRules: [SpecialUnitRuleEnum::Champion],
        );

        // Cult fireteam — Fenton Brahms reference (rulebook p. 23 example).
        $fenton = $this->createUnit(
            slug: 'fenton-brahms',
            name: 'Fenton Brahms',
            title: 'Resident Maniac',
            scrip: 5,
            tactics: '1',
            description: 'Cult of the Burning Man\'s deranged caster.',
            allegiance: $cult,
            standardAvs: ['speed' => 5, 'defense' => 4, 'willpower' => 6, 'armor' => 1],
            gloryAvs: ['speed' => 5, 'defense' => 5, 'willpower' => 7, 'armor' => 2],
            specialRules: [
                [SpecialUnitRuleEnum::Fireteam, ['base_mm' => 30, 'models_per_team' => 1, 'model_size_mm' => 30]],
                SpecialUnitRuleEnum::Unique,
            ],
        );
        $this->attachAction($fenton, ActionTypeEnum::Magic, 'Disintegrating Blast', 8, 'Df', '10"', 2,
            'After winning this duel, discard the top ten cards of your Fate Deck. For each discarded R, increase the Strength of this Action by +1.');

        // Attach a couple of general abilities to several units to populate the pivot.
        $fast = Ability::firstWhere('slug', 'fast');
        $tough = Ability::firstWhere('slug', 'tough');

        if ($fast) {
            $rifle->standardSide()?->abilities()->syncWithoutDetaching([$fast->id => ['sort_order' => 0]]);
        }
        if ($tough) {
            foreach ([$earl, $titan, $abyChamp] as $unit) {
                $unit->standardSide()?->abilities()->syncWithoutDetaching([$tough->id => ['sort_order' => 0]]);
                $unit->glorySide()?->abilities()->syncWithoutDetaching([$tough->id => ['sort_order' => 0]]);
            }
        }
    }

    /**
     * @param  array<int, SpecialUnitRuleEnum|array{0: SpecialUnitRuleEnum, 1: array<string, mixed>}>  $specialRules
     * @param  array{speed: int, defense: int, willpower: int, armor: int}  $standardAvs
     * @param  array{speed: int, defense: int, willpower: int, armor: int}  $gloryAvs
     */
    private function createUnit(
        string $slug,
        string $name,
        ?string $title,
        int $scrip,
        ?string $tactics,
        string $description,
        Allegiance $allegiance,
        array $standardAvs,
        array $gloryAvs,
        array $specialRules,
    ): Unit {
        $unit = Unit::updateOrCreate(
            ['slug' => $slug],
            [
                'name' => $name,
                'title' => $title,
                'scrip' => $scrip,
                'tactics' => $tactics,
                'description' => $description,
            ],
        );

        $unit->allegiances()->syncWithoutDetaching([$allegiance->id]);

        UnitSide::updateOrCreate(
            ['unit_id' => $unit->id, 'side' => UnitSideEnum::Standard->value],
            $standardAvs,
        );
        UnitSide::updateOrCreate(
            ['unit_id' => $unit->id, 'side' => UnitSideEnum::Glory->value],
            $gloryAvs,
        );

        UnitSculpt::updateOrCreate(
            ['slug' => $slug.'-sculpt'],
            [
                'unit_id' => $unit->id,
                'name' => $name,
                'sort_order' => 0,
            ],
        );

        foreach ($specialRules as $entry) {
            if ($entry instanceof SpecialUnitRuleEnum) {
                $rule = $entry;
                $params = null;
            } else {
                [$rule, $params] = $entry;
            }
            $rec = SpecialUnitRule::firstWhere('slug', $rule->value)
                ?? SpecialUnitRule::create(['slug' => $rule->value, 'name' => $rule->label()]);
            $unit->specialUnitRules()->syncWithoutDetaching([
                $rec->id => ['parameters' => $params],
            ]);
        }

        return $unit;
    }

    private function attachAction(
        Unit $unit,
        ActionTypeEnum $type,
        string $name,
        int $av,
        ?string $avTarget,
        string $range,
        ?int $strength,
        string $body,
    ): void {
        $action = Action::updateOrCreate(
            ['slug' => \Illuminate\Support\Str::slug($unit->slug.'-'.$name)],
            [
                'name' => $name,
                'av' => $av,
                'av_target' => $avTarget,
                'range' => $range,
                'strength' => $strength,
                'body' => $body,
            ],
        );
        $action->syncTypes([$type]);

        $unit->standardSide()?->actions()->syncWithoutDetaching([$action->id => ['sort_order' => 0]]);
        $unit->glorySide()?->actions()->syncWithoutDetaching([$action->id => ['sort_order' => 0]]);
    }
}
