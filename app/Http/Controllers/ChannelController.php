<?php

namespace App\Http\Controllers;

use App\Enums\ContentTypeEnum;
use App\Enums\FactionEnum;
use App\Enums\TransmissionTypeEnum;
use App\Models\Channel;
use App\Models\Character;
use App\Models\Keyword;
use App\Models\Transmission;
use Illuminate\Http\Request;

class ChannelController extends Controller
{
    public function index(Request $request): \Inertia\Response|\Inertia\ResponseFactory
    {
        $query = Transmission::with(['channel:id,name,slug,image', 'characters.standardMiniatures', 'keywords'])->latest('release_date');

        $this->applyFilters($query, $request);

        if ($request->filled('channel')) {
            $query->whereHas('channel', fn ($q) => $q->where('slug', $request->input('channel')));
        }

        return inertia('Channels/Index', [
            'transmissions' => $query->get(),
            'channels' => fn () => Channel::orderBy('name')->get()->map(fn (Channel $c) => [
                'name' => $c->name,
                'value' => $c->slug,
            ]),
            ...$this->getFilterOptions(),
        ]);
    }

    public function myChannels(Request $request): \Inertia\Response|\Inertia\ResponseFactory
    {
        $channels = $request->user()->channels()->withCount('transmissions')->orderBy('name')->get();

        return inertia('Channels/MyChannels', [
            'channels' => $channels,
        ]);
    }

    public function view(Request $request, Channel $channel): \Inertia\Response|\Inertia\ResponseFactory
    {
        $query = $channel->transmissions()->with(['characters.standardMiniatures', 'keywords'])->latest('release_date');

        $this->applyFilters($query, $request);

        return inertia('Channels/View', [
            'channel' => $channel->loadMissing('users:id,name'),
            'transmissions' => $query->get(),
            ...$this->getFilterOptions(),
        ]);
    }

    private function getFilterOptions(): array
    {
        return [
            'transmission_types' => TransmissionTypeEnum::toSelectOptions(),
            'content_types' => ContentTypeEnum::toSelectOptions(),
            'factions' => FactionEnum::toSelectOptions(),
            'keywords' => fn () => Keyword::standard()->toSelectOptions('name', 'slug'),
            'characters' => fn () => Character::standard()->toSelectOptions('display_name', 'slug'),
        ];
    }

    private function applyFilters($query, Request $request): void
    {
        if ($request->filled('transmission_type')) {
            $query->where('transmission_type', $request->input('transmission_type'));
        }

        if ($request->filled('content_type')) {
            $query->where('content_type', $request->input('content_type'));
        }

        if ($request->filled('faction')) {
            $query->whereJsonContains('factions', $request->input('faction'));
        }

        if ($request->filled('keyword')) {
            $query->whereHas('keywords', fn ($q) => $q->where('slug', $request->input('keyword')));
        }

        if ($request->filled('character')) {
            $query->whereHas('characters', fn ($q) => $q->where('slug', $request->input('character')));
        }
    }
}
