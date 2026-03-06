<?php

namespace Database\Seeders;

use App\Enums\PoolSeasonEnum;
use App\Enums\SuitEnum;
use App\Models\Scheme;
use App\Models\Strategy;
use Illuminate\Database\Seeder;

class SeasonSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedStrategies();
        $this->seedSchemes();
    }

    private function seedStrategies(): void
    {
        $strategies = [
            [
                'name' => 'Plant Explosives',
                'season' => PoolSeasonEnum::GainingGrounds0,
                'suit' => SuitEnum::Tome,
                'setup' => 'After deployment, each non-peon model in play gains an Explosive token.',
                'rules' => "A model with an Explosive token may take the Interact action to remove the token and make a friendly Strategy marker within 1\", and not within 4\" of another friendly Strategy marker. Explosive tokens may not be removed in any other way.\n\nA model may target a Strategy marker with the Interact action to remove it; if the model does not have an Explosive token, it gains one.\n\nAfter a model with an Explosive token is killed, the model that killed it makes a neutral Strategy marker within 1\" of the killed model, if able.\n\nModels may move on top of Strategy markers.",
                'scoring' => 'At the end of every turn, each crew counts how many friendly Strategy markers they have completely on the enemy table half. The crew with the highest total gains 1 VP. In the case of a tie, both crews gain 1 VP.',
                'additional_scoring' => 'Once per crew per game, at the end of the turn this crew gains 1 VP if there are two or more friendly Strategy markers in the enemy deployment zone.',
                'image' => 'placeholder/strategies/plant-explosives.webp',
            ],
            [
                'name' => 'Boundary Dispute',
                'season' => PoolSeasonEnum::GainingGrounds0,
                'suit' => SuitEnum::Mask,
                'setup' => 'After deployment zones are chosen, starting with the attacker, each player alternates making three Strategy markers completely in their deployment zone, not within 6" of another Strategy marker.',
                'rules' => "Strategy markers are friendly to the player that made them.\n\nA model may target a Strategy marker with the Interact action to place it within 6\" of its current location, not in base contact with any model(s).",
                'scoring' => "At the end of every turn, the crew with the most friendly Strategy markers completely on the enemy table half gains 1 VP. Strategy markers completely in the enemy deployment zone are counted twice. In the case of a tie, both crews gain 1 VP.\n\nThen the crew that has scored the least total VP from this strategy this game may select any one friendly Strategy marker and place it within 4\" of its current location, not in base contact with any model(s).",
                'additional_scoring' => 'Double any victory points gained from this strategy on turn 4.',
                'image' => 'placeholder/strategies/boundary-dispute.webp',
            ],
            [
                'name' => 'Recover Evidence',
                'season' => PoolSeasonEnum::GainingGrounds0,
                'suit' => SuitEnum::Crow,
                'setup' => 'After deployment zones are chosen, starting with the attacker, each player makes one Strategy marker completely on the enemy table half.',
                'rules' => "After a model is killed by the enemy crew, the enemy makes a Strategy marker within 3\" of the killed model.\n\nA model may target a friendly Strategy marker with the Interact action to remove the marker and put it onto that crew's crew card.\n\nModels may move on top of Strategy markers.",
                'scoring' => 'At the end of every turn, the crew with the most Strategy markers on its crew card gains 1 VP. In the case of a tie, both crews gain 1 VP. All crews then remove all Strategy markers from their crew cards.',
                'additional_scoring' => 'Once per crew per game. At the end of any friendly activation, this crew may select a piece of terrain within 6" of the enemy deployment zone and remove a number of friendly Scheme markers equal to the turn number from within 1" of it to gain 1 VP.',
                'image' => 'placeholder/strategies/recover-evidence.webp',
            ],
            [
                'name' => 'Informants',
                'season' => PoolSeasonEnum::GainingGrounds0,
                'suit' => SuitEnum::Ram,
                'setup' => "After deployment zones are chosen, make five Strategy markers: One centered on the centerpoint. Two centered on the centerline, each 10\" to the left and right of the centerpoint, respectively.\n\nStarting with the attacker, each player alternates making one Strategy marker in the center of one table quarter completely on their side of the board.",
                'rules' => 'A crew controls a Strategy marker if it has more models without Summon tokens within 2" of the marker than any opponent does.',
                'scoring' => "At the end of every turn, the crew controlling the most Strategy markers gains 1 VP. In the case of a tie, both crews gain 1 VP.\n\nThen the crew that has scored the least total VP from this strategy this game selects up to two Strategy markers and places them within 3\" of their location, not in base contact with any model(s) or within 8\" of any other Strategy marker(s).",
                'additional_scoring' => 'Double any victory points gained from this strategy on turn 4.',
                'image' => 'placeholder/strategies/informants.webp',
            ],
        ];

        foreach ($strategies as $data) {
            Strategy::create($data);
        }
    }

    private function seedSchemes(): void
    {
        $season = PoolSeasonEnum::GainingGrounds0;

        $schemeData = [
            [
                'name' => 'Breakthrough',
                'selector' => null,
                'prerequisite' => null,
                'reveal' => 'You may reveal this scheme when an enemy model ends its activation.',
                'scoring' => 'When this scheme is revealed, remove one friendly Scheme marker in the enemy deployment zone that does not have an enemy model within 2" of it to gain 1 VP.',
                'additional' => 'When this scheme is revealed you may also remove one friendly Scheme marker from the centerline and one friendly Scheme marker from your deployment zone to gain 1 additional VP.',
                'next' => ['Assassinate', 'Public Demonstration', 'Frame Job'],
            ],
            [
                'name' => 'Frame Job',
                'selector' => null,
                'prerequisite' => 'When this scheme is selected, secretly choose a friendly model.',
                'reveal' => 'You may reveal this scheme after the chosen model suffers damage from an enemy attack action targeting it while it is on the enemy table half.',
                'scoring' => 'When this scheme is revealed, gain 1 VP.',
                'additional' => 'When this scheme is revealed, you may remove one friendly Scheme marker from within 2" of the chosen model to gain 1 additional VP.',
                'next' => ['Public Demonstration', 'Harness the Leyline', 'Scout the Rooftops'],
            ],
            [
                'name' => 'Assassinate',
                'selector' => null,
                'prerequisite' => "When this scheme is selected, secretly choose a unique enemy model that has half or more of its maximum health remaining.\n\nYou may want to ask about the health of multiple models (even if you do not select this scheme) to fool your opponent.",
                'reveal' => 'You may reveal this scheme after the chosen model is reduced to below half of its maximum health.',
                'scoring' => 'When this scheme is revealed, gain 1 VP.',
                'additional' => 'At the end of the turn on which this scheme was revealed, if the chosen model has been killed, gain 1 additional VP.',
                'next' => ['Scout the Rooftops', 'Detonate Charges', 'Runic Binding'],
            ],
            [
                'name' => 'Scout the Rooftops',
                'selector' => null,
                'prerequisite' => null,
                'reveal' => 'You may reveal this scheme at the end of any turn.',
                'scoring' => "Scheme markers are qualifying for this scheme if they:\n- Are not within 6\" of your deployment zone.\n- Do not have an enemy model at the same elevation within 2\".\n- Are at elevation 2 or higher.\n\nWhen this scheme is revealed, remove one qualifying Scheme marker from two different terrain pieces to gain 1 VP (two Scheme markers total).",
                'additional' => 'When this scheme is revealed, select one additional qualifying Scheme marker that is completely on the enemy table half and remove it to gain 1 VP. Note that this Scheme marker may be on the same terrain piece as one of the other qualifying Scheme markers.',
                'next' => ['Detonate Charges', 'Grave Robbing', 'Leave Your Mark'],
            ],
            [
                'name' => 'Detonate Charges',
                'selector' => null,
                'prerequisite' => null,
                'reveal' => 'You may reveal this scheme at the end of any turn.',
                'scoring' => 'When this scheme is revealed, remove two friendly Scheme markers that are within 2" of enemy model(s) to gain 1 VP.',
                'additional' => 'When this scheme is revealed you may remove one additional qualifying marker to gain 1 additional VP.',
                'next' => ['Grave Robbing', 'Runic Binding', 'Take the Highground'],
            ],
            [
                'name' => 'Ensnare',
                'selector' => null,
                'prerequisite' => null,
                'reveal' => 'You may reveal this scheme when an enemy model ends its activation.',
                'scoring' => 'When this scheme is revealed, remove two friendly Scheme markers from within 2" of a single unique enemy model to gain 1 VP.',
                'additional' => 'When this scheme is revealed, if the enemy unique model is engaged by a model of lower cost, gain 1 additional VP.',
                'next' => ['Reshape the Land', 'Search the Area', 'Frame Job'],
            ],
            [
                'name' => 'Make it Look Like an Accident',
                'selector' => null,
                'prerequisite' => null,
                'reveal' => 'You may reveal this scheme when an enemy model suffers damage due to falling.',
                'scoring' => 'When this scheme is revealed, gain 1 VP.',
                'additional' => 'If at the end of the turn on which this scheme was revealed the enemy model that fell has been killed or has less than half of its maximum health, gain 1 additional VP.',
                'next' => ['Ensnare', 'Reshape the Land', 'Breakthrough'],
            ],
            [
                'name' => 'Harness the Leyline',
                'selector' => null,
                'prerequisite' => null,
                'reveal' => 'You may reveal this scheme at the end of any turn.',
                'scoring' => 'When this scheme is revealed, remove two friendly Scheme markers on the centerline not within 6" of another marker used to score this scheme and that do not have any enemy models within 2" to gain 1 VP.',
                'additional' => 'Remove one additional qualifying marker to gain 1 additional VP.',
                'next' => ['Assassinate', 'Scout the Rooftops', 'Grave Robbing'],
            ],
            [
                'name' => 'Search the Area',
                'selector' => null,
                'prerequisite' => null,
                'reveal' => 'You may reveal this scheme at the end of any enemy activation.',
                'scoring' => 'When this scheme is revealed, select a piece of terrain completely on the enemy table half. Remove three friendly Scheme markers from within 1" of the selected terrain that do not have enemy models within 2" of them to gain 1 VP.',
                'additional' => 'At the end of the turn on which this scheme was revealed, you may remove one friendly Scheme marker from within 1" of the selected terrain to gain 1 VP.',
                'next' => ['Breakthrough', 'Frame Job', 'Harness the Leyline'],
            ],
            [
                'name' => 'Take the Highground',
                'selector' => null,
                'prerequisite' => "A crew is considered to control a terrain piece if it has the most friendly models standing on it.\n\nThis crew's models that are within 6\" of their deployment zone are ignored.",
                'reveal' => 'You may reveal this scheme at the end of any turn.',
                'scoring' => 'When you reveal this scheme, if you control at least two Ht 2 or greater terrain pieces gain 1 VP.',
                'additional' => 'If you control at least three qualifying terrain pieces gain 1 additional VP.',
                'next' => ['Make it Look Like an Accident', 'Ensnare', 'Search the Area'],
            ],
            [
                'name' => 'Grave Robbing',
                'selector' => null,
                'prerequisite' => 'When this Scheme is selected, secretly choose a type of non-Scheme marker.',
                'reveal' => 'After killing an enemy model within 2" of both one or more friendly Scheme marker(s) and one or more of the chosen marker, reveal this scheme.',
                'scoring' => 'When this scheme is revealed, remove one friendly Scheme marker within 2" of the killed model to gain 1 VP.',
                'additional' => "Until the end of the turn, friendly models may target enemy Remains markers with the Interact action to remove them and place them on your crew card.\n\nAt the end of the turn remove all Remains markers from your crew card that were placed this way. If two or more are removed, gain 1 additional VP.",
                'next' => ['Runic Binding', 'Leave Your Mark', 'Make it Look Like an Accident'],
            ],
            [
                'name' => 'Runic Binding',
                'selector' => null,
                'prerequisite' => null,
                'reveal' => 'You may reveal this scheme when an enemy model ends its activation.',
                'scoring' => 'When this scheme is revealed, choose three friendly Scheme markers in play. Each chosen marker must be within 14" of at least one of the other chosen markers. If there is at least one enemy model within the area formed between the chosen markers, gain 1 VP. Remove the chosen markers.',
                'additional' => 'When this scheme is revealed, if the combined cost of the enemy models in that area is 15 or greater, gain 1 additional VP.',
                'next' => ['Leave Your Mark', 'Take the Highground', 'Ensnare'],
            ],
            [
                'name' => 'Reshape the Land',
                'selector' => null,
                'prerequisite' => 'When this scheme is selected, secretly choose a marker type.',
                'reveal' => 'You may reveal this scheme at the end of any turn.',
                'scoring' => 'If there are four friendly markers of the chosen type completely on the enemy table half, gain 1 VP. Then, if the chosen marker type was Scheme, remove all markers used to score this Scheme.',
                'additional' => 'If there are five friendly markers of the chosen type completely on the enemy table half, gain 1 additional VP.',
                'next' => ['Search the Area', 'Breakthrough', 'Public Demonstration'],
            ],
            [
                'name' => 'Public Demonstration',
                'selector' => null,
                'prerequisite' => 'When this scheme is selected, secretly choose a unique enemy model.',
                'reveal' => 'You may reveal this scheme at the end of any turn.',
                'scoring' => 'When this scheme is revealed, if there are two or more friendly minions within 2" of the chosen model, gain 1 VP.',
                'additional' => 'When this scheme is revealed, remove a friendly Scheme marker from within 1" of the chosen model to gain 1 additional VP.',
                'next' => ['Harness the Leyline', 'Assassinate', 'Detonate Charges'],
            ],
            [
                'name' => 'Leave Your Mark',
                'selector' => null,
                'prerequisite' => null,
                'reveal' => 'You may reveal this scheme at the end of any turn.',
                'scoring' => 'When this scheme is revealed, if there are more friendly Scheme markers within 1" of the centerpoint than enemy Scheme markers within 1" of the centerpoint, gain 1 VP. Then, remove all friendly Scheme markers within 1" of the centerpoint.',
                'additional' => 'When this scheme is revealed, if there are at least two more friendly Scheme markers within 1" of the centerpoint than enemy Scheme markers within 1" of the centerpoint, gain 1 additional VP.',
                'next' => ['Take the Highground', 'Make it Look Like an Accident', 'Reshape the Land'],
            ],
        ];

        // First pass: create all schemes
        $schemes = [];
        foreach ($schemeData as $data) {
            $schemes[$data['name']] = Scheme::create([
                'name' => $data['name'],
                'season' => $season,
                'selector' => $data['selector'],
                'prerequisite' => $data['prerequisite'],
                'reveal' => $data['reveal'],
                'scoring' => $data['scoring'],
                'additional' => $data['additional'],
                'image' => 'placeholder/schemes/'.str($data['name'])->slug().'.webp',
            ]);
        }

        // Second pass: link next schemes
        foreach ($schemeData as $data) {
            $scheme = $schemes[$data['name']];
            $nextNames = $data['next'];

            $scheme->update([
                'next_scheme_one_id' => $schemes[$nextNames[0]]->id ?? null,
                'next_scheme_two_id' => $schemes[$nextNames[1]]->id ?? null,
                'next_scheme_three_id' => $schemes[$nextNames[2]]->id ?? null,
            ]);
        }
    }
}
