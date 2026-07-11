<?php

namespace App\Http\Requests;

use App\Enums\FactionEnum;
use App\Models\CustomUpgrade;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Shared by CustomUpgradeController::store/update — same shape both ways.
 * See CustomCharacterRequest for the authorize() reasoning (route-bound
 * model on update, any authenticated user on store).
 */
class CustomUpgradeRequest extends FormRequest
{
    public function authorize(): bool
    {
        $customUpgrade = $this->route('customUpgrade');

        return $customUpgrade instanceof CustomUpgrade
            ? ($this->user() !== null && $this->user()->can('update', $customUpgrade))
            : $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'domain' => ['required', 'string', 'in:crew,character'],
            'type' => ['nullable', 'string', 'max:255'],
            // faction is cast to FactionEnum on the model — an invalid value
            // here throws on every subsequent read of the row (edit, index,
            // share, admin moderation), not just a bad-data problem.
            'faction' => ['nullable', 'string', Rule::enum(FactionEnum::class)],
            'limitations' => ['nullable', 'string', 'max:255'],
            'plentiful' => ['nullable', 'integer', 'min:1', 'max:10'],
            'master_name' => ['nullable', 'string', 'max:255'],
            'keyword_name' => ['nullable', 'string', 'max:255'],
            'content_blocks' => ['nullable', 'array', 'max:20'],
            'content_blocks.*.type' => ['required', 'string', 'in:text,ability,action,trigger'],
            'content_blocks.*.text' => ['nullable', 'string', 'max:1000'],
            'content_blocks.*.data' => ['nullable', 'array'],
            // Same v-for="n in stone_cost" soulstone-icon rendering as the
            // character side (UpgradeFrontFace.vue) — bound it for the same
            // reason: an unbounded value hangs/crashes anyone who opens the
            // card, including on the public, unauthenticated share link.
            'content_blocks.*.data.stone_cost' => ['nullable', 'integer', 'min:0', 'max:10'],
            'content_blocks.*.data.triggers' => ['nullable', 'array', 'max:10'],
            'content_blocks.*.data.triggers.*.stone_cost' => ['nullable', 'integer', 'min:0', 'max:10'],
            'content_blocks.*.data.triggers.*.description' => ['nullable', 'string', 'max:2000'],
            'content_blocks.*.data.description' => ['nullable', 'string', 'max:2000'],
            'back_tokens' => ['nullable', 'array', 'max:10'],
            'back_tokens.*.name' => ['required', 'string', 'max:255'],
            'back_tokens.*.description' => ['nullable', 'string', 'max:2000'],
            'back_tokens.*.source_id' => ['nullable', 'integer'],
            'back_markers' => ['nullable', 'array', 'max:10'],
            'back_markers.*.name' => ['required', 'string', 'max:255'],
            'back_markers.*.description' => ['nullable', 'string', 'max:2000'],
            'back_markers.*.source_id' => ['nullable', 'integer'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ];
    }
}
