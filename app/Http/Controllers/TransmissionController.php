<?php

namespace App\Http\Controllers;

use App\Enums\ContentTypeEnum;
use App\Enums\FactionEnum;
use App\Enums\PermissionEnum;
use App\Enums\TransmissionTypeEnum;
use App\Models\Channel;
use App\Models\Character;
use App\Models\Keyword;
use App\Models\Transmission;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class TransmissionController extends Controller
{
    public function create(Request $request, Channel $channel)
    {
        $this->authorizeChannelAccess($channel, $request);

        return inertia('Channels/TransmissionForm', array_merge(
            ['channel' => $channel],
            $this->getFormData(),
        ));
    }

    public function store(Request $request, Channel $channel)
    {
        $this->authorizeChannelAccess($channel, $request);

        $transmission = $this->validateAndSave($request, $channel);

        return redirect()->route('channels.view', $channel)->withMessage("{$transmission->title} created successfully.");
    }

    public function edit(Request $request, Channel $channel, Transmission $transmission)
    {
        $this->authorizeChannelAccess($channel, $request);

        $transmission->loadMissing(['characters', 'keywords']);

        return inertia('Channels/TransmissionForm', array_merge(
            ['channel' => $channel, 'transmission' => $transmission],
            $this->getFormData(),
        ));
    }

    public function update(Request $request, Channel $channel, Transmission $transmission)
    {
        $this->authorizeChannelAccess($channel, $request);

        $transmission = $this->validateAndSave($request, $channel, $transmission);

        return redirect()->route('channels.view', $channel)->withMessage("{$transmission->title} has been updated.");
    }

    public function delete(Request $request, Channel $channel, Transmission $transmission)
    {
        $this->authorizeChannelAccess($channel, $request);

        $title = $transmission->title;
        $transmission->delete();

        return redirect()->route('channels.view', $channel)->withMessage("{$title} has been deleted.");
    }

    private function authorizeChannelAccess(Channel $channel, Request $request): void
    {
        if (! $request->user()->can(PermissionEnum::EditChannel->value)
            && ! $channel->users()->where('user_id', $request->user()->id)->exists()) {
            abort(403, 'You do not have access to manage this channel.');
        }
    }

    private function getFormData(): array
    {
        return [
            'transmission_types' => TransmissionTypeEnum::toSelectOptions(),
            'content_types' => ContentTypeEnum::toSelectOptions(),
            'factions' => FactionEnum::toSelectOptions(),
            'characters' => fn () => Character::standard()->toSelectOptions('display_name', 'slug'),
            'keywords' => fn () => Keyword::standard()->toSelectOptions('name', 'slug'),
        ];
    }

    private function validateAndSave(Request $request, Channel $channel, ?Transmission $transmission = null): Transmission
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'url' => ['required', 'string', 'max:500'],
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

        $validated['channel_id'] = $channel->id;

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
