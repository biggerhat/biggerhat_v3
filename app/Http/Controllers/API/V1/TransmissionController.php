<?php

namespace App\Http\Controllers\API\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\API\V1\TransmissionResource;
use App\Models\Transmission;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * @tags Transmissions
 */
class TransmissionController extends Controller
{
    /**
     * List all transmissions
     *
     * Returns a paginated list of transmissions with their channel, characters, and keywords. Sorted by release date (newest first).
     *
     * @queryParam search string Filter transmissions by title. Example: Errata
     * @queryParam channel string Filter by channel slug. Example: wyrd-chronicles
     * @queryParam transmission_type string Filter by transmission type. Example: errata
     * @queryParam content_type string Filter by content type. Example: pdf
     * @queryParam faction string Filter by faction (matches within the factions JSON array). Example: arcanists
     * @queryParam character string Filter by related character slug. Example: rasputina
     * @queryParam keyword string Filter by related keyword slug. Example: december
     * @queryParam per_page int Number of results per page (max 100). Example: 15
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $transmissions = Transmission::query()
            ->with(['channel', 'characters', 'keywords'])
            ->when($request->query('search'), fn ($q, $search) => $q->where('title', 'LIKE', "%{$search}%"))
            ->when($request->query('channel'), fn ($q, $channel) => $q->whereHas('channel', fn ($cq) => $cq->where('slug', $channel)))
            ->when($request->query('transmission_type'), fn ($q, $type) => $q->where('transmission_type', $type))
            ->when($request->query('content_type'), fn ($q, $type) => $q->where('content_type', $type))
            ->when($request->query('faction'), fn ($q, $faction) => $q->whereJsonContains('factions', $faction))
            ->when($request->query('character'), fn ($q, $char) => $q->whereHas('characters', fn ($cq) => $cq->where('slug', $char)))
            ->when($request->query('keyword'), fn ($q, $kw) => $q->whereHas('keywords', fn ($kq) => $kq->where('slug', $kw)))
            ->latest('release_date')
            ->paginate(min((int) $request->query('per_page', 15), 100));

        return TransmissionResource::collection($transmissions);
    }

    /**
     * Get a single transmission
     *
     * Returns a single transmission with its associated channel, characters, and keywords.
     */
    public function show(Transmission $transmission): TransmissionResource
    {
        $transmission->loadMissing(['channel', 'characters', 'keywords']);

        return new TransmissionResource($transmission);
    }
}
