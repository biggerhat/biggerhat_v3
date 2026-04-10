<?php

namespace App\Http\Controllers\Admin;

use App\Enums\FactionEnum;
use App\Enums\PodSourceEnum;
use App\Http\Controllers\Controller;
use App\Models\Keyword;
use App\Models\Miniature;
use App\Models\PodLink;
use App\Models\Upgrade;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PodLinkAdminController extends Controller
{
    public function index(): \Inertia\Response|\Inertia\ResponseFactory
    {
        return inertia('Admin/PodLinks/Index', [
            'pod_links' => PodLink::orderBy('name', 'ASC')->get(),
        ]);
    }

    public function create()
    {
        return inertia('Admin/PodLinks/PodLinkForm', $this->getFormData());
    }

    public function edit(PodLink $podLink)
    {
        $podLink->loadMissing(['miniatures', 'upgrades', 'keywords']);

        $podData = $podLink->toArray();
        $podData['faction_tags'] = $podLink->faction_tags;

        return inertia('Admin/PodLinks/PodLinkForm', array_merge(
            ['pod_link' => $podData],
            $this->getFormData(),
        ));
    }

    public function store(Request $request)
    {
        $validated = $this->validateRequest($request);
        $podLink = PodLink::create($validated);
        $this->syncRelationships($podLink, $request);

        return redirect()->route('admin.pod_links.index')->withMessage("{$podLink->name} created successfully.");
    }

    public function update(Request $request, PodLink $podLink)
    {
        $validated = $this->validateRequest($request);
        $podLink->update($validated);
        $this->syncRelationships($podLink, $request);

        return redirect()->route('admin.pod_links.index')->withMessage("{$podLink->name} has been updated.");
    }

    public function delete(PodLink $podLink)
    {
        $name = $podLink->name;
        $podLink->delete();

        return redirect()->route('admin.pod_links.index')->withMessage("{$name} has been deleted.");
    }

    private function validateRequest(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'source' => ['required', 'string', Rule::enum(PodSourceEnum::class)],
            'url' => ['required', 'url', 'max:2048'],
        ]);
    }

    private function syncRelationships(PodLink $podLink, Request $request): void
    {
        $miniatureIds = Miniature::whereIn('id', $request->input('miniatures', []))->pluck('id');
        $podLink->miniatures()->sync($miniatureIds);

        $upgradeIds = Upgrade::whereIn('slug', $request->input('upgrades', []))->pluck('id');
        $podLink->upgrades()->sync($upgradeIds);

        $keywordIds = Keyword::whereIn('slug', $request->input('keywords', []))->pluck('id');
        $podLink->keywords()->sync($keywordIds);

        $podLink->syncFactionTags($request->input('factions', []));
    }

    private function getFormData(): array
    {
        return [
            'sources' => PodSourceEnum::toSelectOptions(),
            'all_miniatures' => fn () => Miniature::with('character:id,display_name')
                ->orderBy('display_name')
                ->get(['id', 'display_name', 'character_id'])
                ->map(fn (Miniature $m) => [
                    'name' => $m->display_name.($m->character ? ' ('.$m->character->display_name.')' : ''),
                    'value' => (string) $m->id,
                ]),
            'all_upgrades' => fn () => Upgrade::orderBy('name')->toSelectOptions('name', 'slug'),
            'all_keywords' => fn () => Keyword::orderBy('name')->toSelectOptions('name', 'slug'),
            'all_factions' => fn () => collect(FactionEnum::cases())->map(fn (FactionEnum $f) => [
                'name' => $f->label(),
                'value' => $f->value,
            ])->toArray(),
        ];
    }
}
