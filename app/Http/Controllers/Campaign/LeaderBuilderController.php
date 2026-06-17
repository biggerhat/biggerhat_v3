<?php

namespace App\Http\Controllers\Campaign;

use App\Enums\BaseSizeEnum;
use App\Enums\Campaign\LeaderArchetypeEnum;
use App\Enums\Campaign\LeaderTagEnum;
use App\Enums\FactionEnum;
use App\Http\Controllers\Controller;
use App\Http\Requests\Campaign\StoreLeaderRequest;
use App\Models\Campaign\Campaign;
use App\Models\Campaign\CampaignCrew;
use App\Models\CustomCharacter;
use App\Models\Keyword;
use App\Traits\Campaign\AuthorizesCampaignAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class LeaderBuilderController extends Controller
{
    use AuthorizesCampaignAccess;

    public function edit(Request $request, Campaign $campaign, CampaignCrew $crew)
    {
        $this->ensureCrewOwner($request, $campaign, $crew);

        $existingLeader = CustomCharacter::query()
            ->where('campaign_crew_id', $crew->id)
            ->where('is_campaign_leader', true)
            ->where('current', true)
            ->first();

        return inertia('Campaigns/LeaderBuilder', [
            'campaign' => $campaign->only(['id', 'name', 'status']),
            'crew' => $crew->only(['id', 'share_code', 'name', 'faction', 'keyword_1_id', 'keyword_2_id']),
            'leader' => $existingLeader,
            'archetypes' => LeaderArchetypeEnum::dataset(),
            'archetype_enum' => LeaderArchetypeEnum::toSelectOptions(),
            'tag_enum' => LeaderTagEnum::toSelectOptions(),
            'faction_enum' => FactionEnum::toSelectOptions(),
            'base_enum' => BaseSizeEnum::toSelectOptions(),
            'all_keywords' => fn () => Keyword::query()
                ->select('id', 'name')
                ->orderBy('name')
                ->get(),
        ]);
    }

    public function update(StoreLeaderRequest $request, Campaign $campaign, CampaignCrew $crew)
    {
        $this->ensureCrewOwner($request, $campaign, $crew);

        $data = $request->validated();
        $archetype = LeaderArchetypeEnum::from($data['archetype']);

        // Tag actions with their attack/tactical category in the JSON payload
        // so the renderer can group them. The category field is part of the
        // validated structure but not part of the CustomCharacter shape — we
        // keep it through.
        $actions = array_map(function (array $a) {
            return [
                'name' => $a['name'],
                'type' => $a['type'],
                'category' => $a['category'],
                'is_signature' => (bool) ($a['is_signature'] ?? false),
                'stone_cost' => $a['stone_cost'] ?? 0,
                'range' => $a['range'] ?? null,
                'range_type' => $a['range_type'] ?? null,
                'stat' => $a['stat'] ?? null,
                'stat_suits' => $a['stat_suits'] ?? null,
                'stat_modifier' => $a['stat_modifier'] ?? null,
                'resisted_by' => $a['resisted_by'] ?? null,
                'target_number' => $a['target_number'] ?? null,
                'target_suits' => $a['target_suits'] ?? null,
                'damage' => $a['damage'] ?? null,
                'description' => $a['description'] ?? null,
                'source_id' => $a['source_id'] ?? null,
                'triggers' => $a['triggers'] ?? [],
            ];
        }, $data['actions'] ?? []);

        DB::transaction(function () use ($crew, $archetype, $data, $actions, $request) {
            // Demote any prior current leader to history.
            CustomCharacter::query()
                ->where('campaign_crew_id', $crew->id)
                ->where('is_campaign_leader', true)
                ->where('current', true)
                ->update(['current' => false, 'replaced_at' => now()]);

            // Sync the crew's keyword/faction selections — Leader Build is
            // also where these get nailed down.
            $crew->update([
                'faction' => $data['faction'],
                'keyword_1_id' => $data['keyword_1_id'],
                'keyword_2_id' => $data['keyword_2_id'],
            ]);

            CustomCharacter::create([
                'user_id' => $request->user()->id,
                'campaign_crew_id' => $crew->id,
                'is_campaign_leader' => true,
                'is_campaign_totem' => false,
                'archetype' => $data['archetype'],
                'tag' => $data['tag'],
                'campaign_size' => $data['size'],
                'campaign_df' => $archetype->df(),
                'campaign_wp' => $archetype->wp(),
                'campaign_sp' => $archetype->sp(),
                'campaign_health' => $archetype->health(),
                'current' => true,
                'name' => $data['name'],
                'faction' => $data['faction'],
                'station' => 'master', // Leaders auto-gain the Master characteristic (pg 18).
                'health' => $archetype->health(),
                'defense' => $archetype->df(),
                'willpower' => $archetype->wp(),
                'speed' => $archetype->sp(),
                'size' => $data['size'],
                'base' => $data['base'],
                'cost' => null,
                'generates_stone' => true, // Master cost-0 + stone-generating.
                'is_unhirable' => false,
                'actions' => $actions,
                'abilities' => $data['abilities'] ?? [],
                // Validation already enforces both ids exist (exists:keywords,id),
                // so findOrFail is the right semantics.
                'keywords' => [
                    ['id' => $data['keyword_1_id'], 'name' => Keyword::findOrFail($data['keyword_1_id'])->name],
                    ['id' => $data['keyword_2_id'], 'name' => Keyword::findOrFail($data['keyword_2_id'])->name],
                ],
                'characteristics' => $data['characteristics'] ?? [],
            ]);
        });

        return redirect()->route('campaigns.crews.leader.edit', [$campaign, $crew])
            ->withMessage("{$data['name']} saved.");
    }
}
