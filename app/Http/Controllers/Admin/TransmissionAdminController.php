<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ContentTypeEnum;
use App\Enums\FactionEnum;
use App\Enums\TransmissionTypeEnum;
use App\Http\Controllers\Controller;
use App\Models\Channel;
use App\Models\Character;
use App\Models\Keyword;
use App\Models\Transmission;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class TransmissionAdminController extends Controller
{
    public function index(Request $request): \Inertia\Response|\Inertia\ResponseFactory
    {
        return inertia('Admin/Transmissions/Index', [
            'transmissions' => Transmission::with('channel:id,name,slug')->orderBy('created_at', 'DESC')->get(),
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

    public function store(Request $request)
    {
        $transmission = $this->validateAndSave($request);

        return redirect()->route('admin.transmissions.index')->withMessage("{$transmission->title} created successfully.");
    }

    public function update(Request $request, Transmission $transmission)
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

    private function validateAndSave(Request $request, ?Transmission $transmission = null): Transmission
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'url' => ['required', 'string', 'max:500'],
            'channel_id' => ['required', 'integer', 'exists:channels,id'],
            'transmission_type' => ['required', 'string', Rule::enum(TransmissionTypeEnum::class)],
            'content_type' => ['required', 'string', Rule::enum(ContentTypeEnum::class)],
            'factions' => ['nullable', 'array'],
            'factions.*' => ['string', Rule::enum(FactionEnum::class)],
            'release_date' => ['nullable', 'date'],
            'characters' => ['nullable', 'array'],
            'keywords' => ['nullable', 'array'],
        ]);

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
