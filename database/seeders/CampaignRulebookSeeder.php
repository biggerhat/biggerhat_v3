<?php

namespace Database\Seeders;

use App\Enums\GameModeTypeEnum;
use App\Models\Upgrade;
use Illuminate\Database\Seeder;

/**
 * Real Campaign equipment and injury entries from the "Index of the Untold"
 * book (Aftermath, pg 22-30 equipment + Those Who Thirst, pg 34-35 injuries),
 * so the Barter and Determine Injuries phases can be exercised with realistic
 * data. Effect text is condensed; the book is the source of truth.
 *
 * Pools: "R or M" => ram/mask (injury pool `pc`), "C or T" => crow/tome (`te`).
 * Idempotent (keyed on kind + name + value/pool). Dev/CI only — real catalog
 * data is entered through the admin UI.
 */
class CampaignRulebookSeeder extends Seeder
{
    private const RM = ['ram', 'mask'];

    private const CT = ['crow', 'tome'];

    public function run(): void
    {
        foreach ($this->equipment() as [$name, $br, $cc, $pool, $text]) {
            $this->upsert(['campaign_upgrade_kind' => 'equipment', 'name' => $name], [
                'description' => $text,
                'campaign_br' => is_int($br) ? $br : null,
                'campaign_cc' => $cc,
                'campaign_pool_suit_a' => $pool[0] ?? null,
                'campaign_pool_suit_b' => $pool[1] ?? null,
                'campaign_is_always_available' => $br === 'always',
                'campaign_ttw_only' => false,
            ]);
        }

        foreach ($this->thoseWhoThirst() as [$name, $value, $cc, $text]) {
            $this->upsert(['campaign_upgrade_kind' => 'equipment', 'name' => $name], [
                'description' => $text,
                'campaign_br' => $value,
                'campaign_cc' => $cc,
                'campaign_ttw_only' => true,
                'campaign_is_always_available' => false,
            ]);
        }

        // Either joker on the Those Who Thirst flip (pg 30): free, mandatory, and travels with the killer.
        $this->upsert(['campaign_upgrade_kind' => 'equipment', 'name' => 'Omen’s Mark'], [
            'description' => 'Free and mandatory. Attach to a model each encounter; it suffers +1 damage. When killed by an enemy, annihilate this and attach an Omen’s Mark to the model that got kill credit. Cannot be discarded or annihilated any other way. May be held alongside another Those Who Thirst item.',
            'campaign_br' => null,
            'campaign_cc' => 0,
            'campaign_ttw_only' => true,
            'campaign_is_omens_mark' => true,
            'campaign_is_always_available' => false,
        ]);

        foreach ($this->injuries() as [$name, $value, $pool, $text, $flags]) {
            $this->upsert(['campaign_upgrade_kind' => 'injury', 'name' => $name, 'campaign_flip_value' => $value, 'campaign_suit_pool' => $pool], [
                'description' => $text,
                'campaign_is_traitor' => $flags['traitor'] ?? false,
                'campaign_is_close_call' => $flags['close_call'] ?? false,
                'campaign_annihilates_model' => $flags['kills'] ?? false,
                'campaign_reflip_if_master_or_totem' => $flags['reflip_mt'] ?? false,
                'campaign_reflip_if_no_triggers' => $flags['reflip_triggers'] ?? false,
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $keys
     * @param  array<string, mixed>  $values
     */
    private function upsert(array $keys, array $values): void
    {
        Upgrade::query()->updateOrCreate(
            $keys + ['game_mode_type' => GameModeTypeEnum::Campaign->value],
            $values,
        );
    }

    /** @return list<array{0: string, 1: int|string, 2: int, 3: list<string>, 4: string}> */
    private function equipment(): array
    {
        $rm = self::RM;
        $ct = self::CT;

        return [
            ['Lucky Gremlin Foot', 'always', 1, [], 'When this model would attach an injury upgrade, it may annihilate this equipment to not do so.'],
            ['Pistol', 'always', 1, [], 'Ranged attack, Skl 5 Df, Dmg 2. Choose range 1" or 8" when declared.'],
            ['Sword', 'always', 1, [], 'Melee attack, 1", Skl 5 Df, Dmg 2.'],
            ['Trusty Rifle', 'always', 1, [], 'Ranged attack, 14", Skl 5 Df, Dmg 2.'],
            ['Helmet', 1, 2, $rm, 'Hard to Kill: with 2+ health, damage cannot reduce this model below 1 health.'],
            ['Healing Salve', 1, 1, $rm, 'When this model activates, it may annihilate this equipment to heal (see book, pg 22).'],
            ['Blackjack', 1, 2, $rm, 'Melee Skl 5 Df, Dmg 1. Move the target up to 2" (+2" per raise).'],
            ['Leg Breaker', 1, 2, $ct, 'Aim to Maim: on a raise, the target flips on the injury table (cannot be cheated).'],
            ['Warming Flask', 1, 2, $ct, 'Friendly only. Target heals 2, +1 per raise.'],
            ['Lead-Lined Coat', 1, 3, $ct, 'Armor: once per activation, reduce damage dealt to this model by 1.'],
            ['Flamethrower', 2, 2, $rm, 'Ranged 10", Skl 6 Sp, Dmg 2. Enemies within 1" of the target gain Burning.'],
            ['Stage Hook', 2, 2, $rm, 'Another friendly model only. Move the target up to 4".'],
            ['Guardian’s Shield', 2, 2, $rm, 'Juggernaut: reduces all damage dealt to this model to 3.'],
            ['Death Curse', 2, 2, $ct, 'Demise: after being killed by an enemy, that enemy flips on the injury table (cannot be cheated).'],
            ['Twin Katanas', 2, 3, $ct, 'Melee Skl 6 Df, Dmg 2. May drain a soulstone once per turn to engage and declare another melee action.'],
            ['Thieves’ Tools', 2, 2, $ct, 'Melee Skl 6 Df, Dmg 1. On a raise, annihilate an equipment upgrade from the target and copy it.'],
            ['Breakable Rope', 3, 1, $rm, 'Tuck and Roll: discard to place within 6" at a different elevation.'],
            ['Carrier Pigeon', 3, 2, $rm, 'Document Delivery: ally only, make a Scheme marker in base contact with the target.'],
            ['Razor Knife', 3, 2, $rm, 'Melee Skl 6 Df, Dmg 1. On a raise, may declare this action again.'],
            ['Vengeful Vow', 3, 2, $ct, 'Leader only. After killing an enemy unique model, annihilate this to gain +1 experience.'],
            ['Aetheric Displacer', 3, 3, $ct, 'Leap Aside: after an enemy attack targeting this model fails, place this model within 3".'],
            ['Spiteful Medicine', 3, 1, $ct, 'One + to duels on attack actions per injury upgrade attached.'],
            ['Coffee', 4, 1, $rm, 'When this model activates, annihilate this to gain a Fast token.'],
            ['Sniper’s Scope', 4, 1, $rm, 'This model’s ranged actions ignore cover and concealment.'],
            ['Strange Seed Pod', 4, 2, $rm, 'Non-unique only. “It Awakens!”: annihilate to summon a copy of this model within 3".'],
            ['Fool’s Gold', 4, 2, $ct, 'At end of activation, annihilate to make a Strategy marker within 1".'],
            ['Useless Generator', 4, 3, $ct, 'Sputtering Exhaust: the area within 2" is concealing terrain; friendly models may ignore it.'],
            ['Gatling Gun', 4, 2, $ct, 'Ranged 12", Skl 6 Df, Dmg 2. + if the target has no cover.'],
            ['Snake Oil', 5, 1, $rm, 'Discard to remove any number of tokens from this model, then infuse if one was removed.'],
            ['Quickdraw Pistol', 5, 1, $rm, 'Speed Pistol: ranged 8", Skl 5 Df, Dmg 2, declared as a quick action.'],
            ['Trekking Poles', 5, 1, $rm, 'This model is unaffected by severe and hazardous terrain.'],
            ['Assassin’s Blade', 5, 2, $ct, 'Melee Skl 5 Df, Dmg 1. Target discards a card or drains a soulstone, or is killed.'],
            ['Two Kids in a Trench Coat', 5, 2, $ct, 'Demise: when killed, summon a cost 4 or less model sharing a keyword within 1".'],
            ['The Midnight Watch', 5, 1, $ct, 'Herald of the End: ranged 8", Skl 6 Wp, Dmg 1+X where X is the turn number.'],
            ['Whiskey', 6, 1, $rm, 'Calm Nerves: discard on activation to gain a + to all duels until the end phase.'],
            ['Clockwork Grenade', 6, 2, $rm, 'Make a Scheme marker within 8"; enemies within 2" pass a TN 14 Sp duel or take 2 damage.'],
            ['Escape Coil', 6, 2, $rm, 'After an enemy damages this model, may drain a soulstone to place into base contact with a friendly within 6".'],
            ['Hag’s Kiss', 6, 2, $ct, 'Melee 2", Skl 5 Wp, Dmg 2. The target gains Stunned and Slow.'],
            ['Empathic Amplifier', 6, 2, $ct, 'Ranged 4", Skl 5 Wp, Dmg 1. On a raise, move an injury from this model to the target.'],
            ['Trickster’s Mask', 6, 1, $ct, 'Enemy models that activate engaged with this model may not declare Interact.'],
            ['Metal Skull Plate', 7, 3, $rm, 'Confused and Enraged: may declare Charge if the top discard is a ram.'],
            ['Barbed Whip', 7, 2, $rm, 'Melee 4", Skl 6 Df, Dmg 2.'],
            ['Lasso', 7, 2, $rm, 'Ranged 12", Skl 6 Sp. Move the target up to its Sp toward this model; Slow on a raise.'],
            ['Trash Can', 7, 3, $ct, 'Once per activation, target a marker within 2" and remove it.'],
            ['Trash Can’t', 7, 2, $ct, 'Friendly Scheme markers within 2" cannot be removed by enemy actions and abilities.'],
            ['Arcane Sense', 7, 1, $ct, 'Discard on activation and choose a suit to add to final duel totals until the end phase.'],
            ['Back Alley Hydraulics', 8, 1, $rm, 'Headstart: if the top discard is a mask, move up to 3" on activation.'],
            ['Book of Insults', 8, 1, $rm, 'Shouted Insults: ranged 6", Skl 5 Wp, Dmg 1. Target gains Slow.'],
            ['Cursed Mirror', 8, 1, $rm, 'After this model is damaged by an enemy, discard to deal the same damage back.'],
            ['Bayou Recipe Book', 8, 2, $ct, 'Expanded Palate: remove Remains markers within 3" and heal 2 per marker.'],
            ['Poisoned Noose', 8, 2, $ct, 'Ranged 12", Skl 7 Sp. Move the target toward this model; Poison on a raise.'],
            ['False Face', 8, 2, $ct, 'Lure: ranged 12", Skl 5 Wp, TN 12. May target friendly models; move the target toward this model.'],
            ['Strange Geode', 9, 2, $rm, 'Shifting Earth: on activation, place an ally within 3" anywhere within 3" of this model.'],
            ['Unstable Disruptor', 9, 2, $rm, 'Friendly only. Remove a token from the target; on a raise place it within 6".'],
            ['Spinning Scythe', 9, 2, $rm, 'Melee Skl 5 Df, Dmg 2. Enemy models within 1" of this model take 1 damage.'],
            ['Ominous Cloak', 9, 3, $ct, 'Incorporeal: reduce damage dealt to this model by 1.'],
            ['Spyglass', 9, 1, $ct, 'If the top discard is a tome, +4" range on ranged and magic actions until the end phase.'],
            ['Fool’s Stone', 9, 3, $ct, 'False Claim: cannot be declared while engaged. Make two Scheme markers within 3".'],
            ['Mysterious Talisman', 10, 1, $rm, 'Ignore the Merchant’s Warning: when damaged by an enemy, annihilate and flip (uncheatable) for a random effect.'],
            ['Flash Bang', 10, 2, $rm, 'Ranged 8", Skl 5 Df, Dmg 1. Target gains Stunned; on a raise, enemies within 2" too.'],
            ['Mark of Authority', 10, 2, $rm, 'Commanding Presence: when declaring Walk, discard so friendlies within 3" may move up to 4".'],
            ['Crow’s Foot', 10, 2, $ct, 'If the top discard is a crow, deal 2 irreducible damage to this model to gain Fast.'],
            ['Protective Engravings', 10, 3, $ct, 'Aegis: once per activation, reduce damage dealt to this model by 1.'],
            ['Mutagen Injector', 10, 2, $ct, 'Friendly only, once per turn. Target heals 2; jokers trigger an injury or an ability advancement.'],
            ['Duelist’s Rapier', 11, 2, $rm, 'Melee Skl 6 Df, Dmg 2. Ignores armor, shielded and incorporeal abilities.'],
            ['Wax and Feathers', 11, 3, $rm, 'Flight: this model ignores terrain and other models while moving.'],
            ['Macuahuitl', 11, 2, $rm, 'Melee 2", Skl 5 Df, Dmg 3. May gain a Hunger token for +1 damage.'],
            ['Quick Draw Holster', 11, 1, $ct, 'Act Quickly: discard so the next action declared may be treated as a quick action.'],
            ['Retractable Spikes', 11, 1, $ct, 'This model gains an Aura (Hazardous) token.'],
            ['Ancient Scrolls', 11, 2, $ct, 'Ranged 8", Skl 5 Wp, Dmg 2. Each enemy killed draws an additional aftermath card.'],
            ['Strange Portal', 12, 1, $rm, 'When this model activates, annihilate and flip (uncheatable) for a portal effect.'],
            ['Badge of Honor', 12, 3, $rm, 'Heroic Intervention: pull friendly models off an engaged enemy, then engage it and attack.'],
            ['Neverborn Hide', 12, 2, $rm, 'After this model suffers damage, enemy models within 1" gain Injured.'],
            ['Spectral Blade', 12, 2, $ct, 'Melee Skl 6 Df, Dmg 2. Ignores incorporeal; infuse per raise.'],
            ['Giant Pink Sombrero', 12, 3, $ct, 'Diversion: enemy models within 4" count all melee symbols on their cards as blank.'],
            ['Duplicator', 12, 2, $ct, 'Choose a friendly marker within range and make a copy of it.'],
            ['Relic Hammer', 13, 3, $rm, 'Melee 2", Skl 6 Df, Dmg 3. Cannot be controlled by the enemy; + against masters.'],
            ['Flak Jacket', 13, 1, $rm, 'Bulletproof: reduce all damage dealt to this model by ranged actions by 1.'],
            ['Soul Cage', 13, 2, $rm, 'After killing a non-unique enemy, annihilate to summon a copy at half maximum health.'],
            ['Dark Crystal', 13, 1, $ct, 'Ignore the Old Woman’s Advice: on Interact, annihilate and flip (uncheatable) for a random effect.'],
            ['Hurled Luggage', 13, 2, $ct, 'Ranged 8", Skl 6 Df. Make a Scheme marker in base contact with the target.'],
            ['Dead Man’s Switch', 13, 1, $ct, 'Demise: enemy models within 3" take 3 irreducible damage.'],
        ];
    }

    /** @return list<array{0: string, 1: int, 2: int, 3: string}> */
    private function thoseWhoThirst(): array
    {
        $laidToRest = ' Laid to Rest: after killing, the target performs two separate injury flips in aftermath.';

        return [
            ['The Book of the Dead', 1, 3, 'Ranged 12", Skl 6 Wp, Dmg 3. Summon a Mindless Zombie in base contact with the target.'.$laidToRest],
            ['Judgement', 2, 3, 'Melee 2", Skl 6 Df, Dmg 3. Ignores all armor, shielded and incorporeal abilities.'.$laidToRest],
            ['Medusa', 3, 3, 'Ranged 12", Skl 7 Sp, Dmg 1. Target gains Slow, Stunned and Entranced.'.$laidToRest],
            ['Vicious Thorn', 4, 3, 'Melee 4", Skl 6 Df, Dmg 2. Target gains Poison and Suppressed.'.$laidToRest],
            ['Edict', 5, 3, 'Ranged 12", Skl 6 Df, Dmg 3. Make a Scheme marker in base contact with the target.'.$laidToRest],
            ['Blight', 6, 3, 'Melee 2", Skl 6 Wp, Dmg 3. After killing, may infuse.'.$laidToRest],
            ['Insight', 7, 3, 'Melee 4", Skl 6 Df, Dmg 3. May look at the top two cards of the fate deck and discard any.'.$laidToRest],
            ['Rigged Fate Deck', 8, 1, 'May annihilate during activation to discard any number of cards and draw that many +2.'],
        ];
    }

    /** @return list<array{0: string, 1: int|null, 2: string, 3: string, 4: array<string, bool>}> */
    private function injuries(): array
    {
        $rows = [];

        foreach ([
            ['Just a Flesh Wound', 1, 'No injury is gained.', []],
            ['Just a Flesh Wound', 2, 'No injury is gained.', []],
            ['Severe Amputation', 3, 'Reduce this model’s maximum health by 2.', []],
            ['Pack Mule', 4, 'When killed, the opposing player may place a Scheme marker in base contact with it first.', []],
            ['Headstrong', 5, 'Increase this model’s soulstone cost by 1. Master or totem: reflip.', ['reflip_mt' => true]],
            ['Permanent Hex', 6, 'Must deal 1 damage to itself to declare triggers. No triggers: reflip.', ['reflip_triggers' => true]],
            ['Senseless', 7, 'Must discard a card at the start of its activation or gain Slow.', []],
            ['Mangled Limb', 8, 'Suffers -1 Skl to all attack actions. No attack actions: reflip.', []],
            ['Leadfooted', 9, 'This model suffers -1 Sp.', []],
            ['Defenseless', 10, 'When this model is dealt damage, it is dealt +1 damage.', []],
            ['Loose Lips', 11, 'Enemy models within 3" may declare actions printed on this model’s card.', []],
            ['Blood Debt', 12, 'If it ends its activation in the enemy deployment zone it may drain a soulstone to annihilate this; at game end the enemy may annihilate it for 1 VP.', []],
            ['Killed Off', 13, 'Annihilate this model.', ['kills' => true]],
        ] as [$name, $value, $text, $flags]) {
            $rows[] = [$name, $value, 'pc', $text, $flags];
        }

        foreach ([
            ['Just a Flesh Wound', 1, 'No injury is gained.', []],
            ['Just a Flesh Wound', 2, 'No injury is gained.', []],
            ['Distracted by Voices', 3, 'This model suffers -2 Wp.', []],
            ['Always Wandering', 4, 'Deploys during the start phase of turn 2; annihilate this upgrade after deploying.', []],
            ['Fugitive', 5, 'The next time this model is killed, the opposing crew gains 2 scrip. Then annihilate this injury.', []],
            ['One Last Job', 6, 'After this model resolves Interact, it is killed. Insignificant: reflip.', []],
            ['Off Balance', 7, 'This model suffers a - to Df and Wp duels.', []],
            ['Barely Holding Together', 8, 'This model may not declare Charge.', []],
            ['Dulled Edge', 9, 'This model suffers -1 Df.', []],
            ['Missing Fingers', 10, 'On activation may discard a card or treat melee symbols as blank until the end phase. No melee symbols: reflip.', []],
            ['Brittle Bones', 11, 'When targeted by an attack action, the attacker receives a +.', []],
            ['Black Mailed', 12, 'When this model ends its activation the enemy may annihilate this to have it declare an action of their choice.', []],
            ['Killed Off', 13, 'Annihilate this model.', ['kills' => true]],
        ] as [$name, $value, $text, $flags]) {
            $rows[] = [$name, $value, 'te', $text, $flags];
        }

        $rows[] = ['Traitor', null, 'black_joker', 'Leader/totem: reflip. Annihilate this model; the opposing crew may add a copy to its arsenal for free, keeping injuries and equipment.', ['traitor' => true]];
        $rows[] = ['Close Call', null, 'red_joker', 'If the red joker was cheated, no injury. If flipped, flip on the Lucky Miss table.', ['close_call' => true]];

        return $rows;
    }
}
