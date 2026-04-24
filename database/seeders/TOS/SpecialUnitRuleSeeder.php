<?php

namespace Database\Seeders\TOS;

use App\Enums\TOS\SpecialUnitRuleEnum;
use App\Models\TOS\SpecialUnitRule;
use Illuminate\Database\Seeder;

class SpecialUnitRuleSeeder extends Seeder
{
    /**
     * Canonical Special Unit Rules from the rulebook (p. 10–11).
     */
    public function run(): void
    {
        $rows = [
            [
                'enum' => SpecialUnitRuleEnum::Unique,
                'description' => 'You may only have one of this unit in your Company.',
            ],
            [
                'enum' => SpecialUnitRuleEnum::Fireteam,
                'description' => 'Every unit is made up of Fireteams. This rule tells you the base size of the Fireteam(s) in the unit (Q). If a Fireteam is made up of multiple models, it will also tell you how many models make up the Fireteam (Y) and their size (Z).',
            ],
            [
                'enum' => SpecialUnitRuleEnum::Titan,
                'description' => 'Titans are the huge models that dominate a battlefield. Engagements affect Titans differently, and they have slightly different rules with regards to Line of Sight. When moving, a Titan may move through non-Titan Fireteams, but it cannot end its movement on top of another Fireteam.',
            ],
            [
                'enum' => SpecialUnitRuleEnum::Commander,
                'description' => 'Commanders are leaders of armies. They provide the Scrip necessary to hire the rest of your Company. When a Commander receives an Order, it may take a free ! Action in addition to any other Actions allowed by the received Order.',
            ],
            [
                'enum' => SpecialUnitRuleEnum::Squad,
                'description' => 'Squads are collections of soldiers who have trained together. They are represented by multiple Fireteams that form a single unit. When a Squad is hired, it comes with a number of Fireteams equal to the number listed after the Squad rule. All Fireteams in a Squad must remain in Formation (within 8" of every other Fireteam in the Squad).',
            ],
            [
                'enum' => SpecialUnitRuleEnum::Champion,
                'description' => 'Champions are awe-inspiring veterans capable of having a significant impact on the battlefield. When it suffers damage, a Champion Fireteam may choose to remove any number of models from a single friendly Squad Fireteam within 3" to reduce incoming damage by an equal amount.',
            ],
            [
                'enum' => SpecialUnitRuleEnum::CombinedArms,
                'description' => 'Some units contain a Fireteam that functions differently from the rest of the unit, represented by a special Unit Card. When a unit with the Combined Arms rule is hired, it automatically includes the special Unit Card listed in parenthesis after the rule.',
            ],
            [
                'enum' => SpecialUnitRuleEnum::Reserves,
                'description' => 'A unit with Reserves (X) does not deploy at the start of the game. Instead, it may be brought onto the table during one of the X turns specified by the rule.',
            ],
        ];

        $sortOrder = 0;
        foreach ($rows as $row) {
            SpecialUnitRule::updateOrCreate(
                ['slug' => $row['enum']->value],
                [
                    'name' => $row['enum']->label(),
                    'description' => $row['description'],
                    'sort_order' => $sortOrder++,
                ]
            );
        }
    }
}
