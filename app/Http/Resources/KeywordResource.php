<?php

namespace App\Http\Resources;

use App\Models\Character;
use App\Models\Keyword;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Keyword */
class KeywordResource extends JsonResource
{
    public function __construct(Keyword $resource)
    {
        parent::__construct($resource);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $crewUpgrades = collect();
        if ($this->relationLoaded('masters')) {
            $this->loadMissing('masters.crewUpgrades');
            $this->masters->each(function (Character $master) use ($crewUpgrades) {
                $crewUpgrades->push($master->crewUpgrades);
            });
            $crewUpgrades = $crewUpgrades->flatten(2);
        }

        return [
            'name' => $this->name,
            'slug' => $this->slug,
            'has_master' => (bool) count($this->masters),
            'masters' => $this->when($this->relationLoaded('masters'), fn () => $this->masters, []),
            'crew_upgrades' => $crewUpgrades->values()->all(),
            'characters' => $this->when($this->relationLoaded('characters'), fn () => $this->characters, []),
            'characters_count' => $this->when($this->relationLoaded('characters'), fn () => count($this->characters), 0),
        ];
    }
}
