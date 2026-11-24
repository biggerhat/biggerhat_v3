<?php

namespace App\Http\Requests\Stats;

use App\Enums\FactionEnum;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** The filters on a player's analytics page, read from the address so a view can be shared. All of them are optional. */
class AnalyticsFilterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
            'sources' => ['nullable', 'array'],
            'sources.*' => ['string', Rule::in(['tracked', 'logged', 'tournament'])],
            // games played against yourself are in by default; solo=0 leaves them out
            'solo' => ['nullable', 'boolean'],
            'result' => ['nullable', Rule::in(['win', 'loss', 'tie'])],
            'faction' => ['nullable', 'string', Rule::enum(FactionEnum::class)],
            'opponent_faction' => ['nullable', 'string', Rule::enum(FactionEnum::class)],
            'master' => ['nullable', 'string', 'max:120'],
            'opponent' => ['nullable', 'string', 'max:120'],
            'strategy' => ['nullable', 'string', 'max:120'],
            'deployment' => ['nullable', 'string', 'max:120'],
            'size' => ['nullable', 'integer', 'min:1', 'max:200'],
            'season' => ['nullable', 'string', 'max:120'],
            'play_mode' => ['nullable', 'string', 'max:120'],
            'venue' => ['nullable', 'string', 'max:120'],
        ];
    }

    /**
     * The filters as the analytics take them: only what was set.
     *
     * @return array<string, mixed>
     */
    public function filters(): array
    {
        $v = $this->validated();
        $out = array_filter($v, fn ($x) => $x !== null && $x !== '' && $x !== []);
        if (array_key_exists('solo', $v)) {
            $out['solo'] = (bool) $v['solo'];
        }

        return $out;
    }
}
