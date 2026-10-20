<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ContentTypeEnum;
use App\Enums\FactionEnum;
use App\Enums\TransmissionTypeEnum;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\TransmissionRequest;
use App\Models\Channel;
use App\Models\Character;
use App\Models\Keyword;
use App\Models\Transmission;
use Illuminate\Http\Request;

class TransmissionAdminController extends Controller
{
    public function index(Request $request): \Inertia\Response|\Inertia\ResponseFactory
    {
        $search = trim($request->string('q')->toString());

        $transmissions = Transmission::query()
            ->with('channel:id,name,slug')
            ->when($search !== '', fn ($q) => $q->where('title', 'like', '%'.addcslashes($search, '%_\\').'%'))
            ->orderByDesc('created_at')
            ->paginate(25)
            ->withQueryString();

        return inertia('Admin/Transmissions/Index', [
            'transmissions' => $transmissions,
            'filters' => ['q' => $search],
        ]);
    }

    public function create(Request $request)
    {
        return inertia('Admin/Transmissions/TransmissionForm', $this->getFormData());
    }

    public function edit(Request $request, Transmission $transmission)
    {
        $transmission->loadMissing(['channel', 'characters', 'keywords']);

        return inertia('Admin/Transmissions/TransmissionForm', array_merge(
            ['transmission' => $transmission],
            $this->getFormData(),
        ));
    }

    public function store(TransmissionRequest $request)
    {
        $transmission = $this->validateAndSave($request);

        return redirect()->route('admin.transmissions.index')->withMessage("{$transmission->title} created successfully.");
    }

    public function update(TransmissionRequest $request, Transmission $transmission)
    {
        $transmission = $this->validateAndSave($request, $transmission);

        return redirect()->route('admin.transmissions.index')->withMessage("{$transmission->title} has been updated.");
    }

    public function delete(Request $request, Transmission $transmission)
    {
        $title = $transmission->title;
        $transmission->delete();

        return redirect()->route('admin.transmissions.index')->withMessage("{$title} has been deleted.");
    }

    private function getFormData(): array
    {
        return [
            'channels' => fn () => Channel::orderBy('name')->get()->map(fn (Channel $c) => [
                'name' => $c->name,
                'value' => (string) $c->id,
            ]),
            'transmission_types' => TransmissionTypeEnum::toSelectOptions(),
            'content_types' => ContentTypeEnum::toSelectOptions(),
            'factions' => FactionEnum::toSelectOptions(),
            'characters' => fn () => Character::toSelectOptions('display_name', 'slug'),
            'keywords' => fn () => Keyword::toSelectOptions('name', 'slug'),
        ];
    }

    private function validateAndSave(TransmissionRequest $request, ?Transmission $transmission = null): Transmission
    {
        $validated = $request->validated();

        $characterSlugs = $validated['characters'] ?? [];
        $keywordSlugs = $validated['keywords'] ?? [];
        unset($validated['characters'], $validated['keywords']);

        if (! $transmission) {
            $transmission = Transmission::create($validated);
        } else {
            $transmission->update($validated);
        }

        $transmission->characters()->sync(Character::whereIn('slug', $characterSlugs)->pluck('id'));
        $transmission->keywords()->sync(Keyword::whereIn('slug', $keywordSlugs)->pluck('id'));

        return $transmission;
    }
}
