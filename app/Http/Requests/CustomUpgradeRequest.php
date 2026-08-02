<?php

namespace App\Http\Requests;

use App\Enums\ActionRangeTypeEnum;
use App\Enums\ActionTypeEnum;
use App\Enums\DefensiveAbilityTypeEnum;
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
            // `data` is shared by ability/action/trigger blocks (text blocks
            // carry no `data` at all), so this is the union of every field
            // UpgradeEditor.vue sends across all three shapes — all nullable
            // rather than required (unlike the equivalent CustomCharacterRequest
            // arrays) since a missing `data` key for a text block would
            // otherwise trip a "required" failure on an unrelated block type.
            // Without an explicit rule here, validated() silently drops the
            // field on every save — this was the root cause of both "editing a
            // Crew Card a second time blanks the Actions" (name/type/etc. never
            // persisted past the first save) and "edits break the campaign
            // action stat link" (source_id silently dropped).
            'content_blocks.*.data.name' => ['nullable', 'string', 'max:255'],
            'content_blocks.*.data.type' => ['nullable', 'string', Rule::enum(ActionTypeEnum::class)],
            'content_blocks.*.data.is_signature' => ['boolean'],
            // Same v-for="n in stone_cost" soulstone-icon rendering as the
            // character side (UpgradeFrontFace.vue) — bound it for the same
            // reason: an unbounded value hangs/crashes anyone who opens the
            // card, including on the public, unauthenticated share link.
            'content_blocks.*.data.stone_cost' => ['nullable', 'integer', 'min:0', 'max:10'],
            'content_blocks.*.data.range' => ['nullable', 'max:20'],
            'content_blocks.*.data.range_type' => ['nullable', 'string', Rule::enum(ActionRangeTypeEnum::class)],
            'content_blocks.*.data.stat' => ['nullable', 'max:20'],
            'content_blocks.*.data.stat_suits' => ['nullable', 'string', 'max:20'],
            'content_blocks.*.data.stat_modifier' => ['nullable', 'string', 'max:20'],
            'content_blocks.*.data.resisted_by' => ['nullable', 'string', 'max:20'],
            'content_blocks.*.data.target_number' => ['nullable', 'max:20'],
            'content_blocks.*.data.target_suits' => ['nullable', 'string', 'max:20'],
            'content_blocks.*.data.damage' => ['nullable', 'string', 'max:50'],
            'content_blocks.*.data.suits' => ['nullable', 'string', 'max:20'],
            'content_blocks.*.data.defensive_ability_type' => ['nullable', 'string', Rule::enum(DefensiveAbilityTypeEnum::class)],
            'content_blocks.*.data.costs_stone' => ['boolean'],
            // Preserves the Leader Builder's "which official row this was
            // picked from" link through a save made in this generic editor —
            // dropped, this silently breaks the campaign action-stat link.
            'content_blocks.*.data.source_id' => ['nullable', 'integer'],
            'content_blocks.*.data.triggers' => ['nullable', 'array', 'max:10'],
            'content_blocks.*.data.triggers.*.name' => ['nullable', 'string', 'max:255'],
            'content_blocks.*.data.triggers.*.suits' => ['nullable', 'string', 'max:20'],
            'content_blocks.*.data.triggers.*.stone_cost' => ['nullable', 'integer', 'min:0', 'max:10'],
            'content_blocks.*.data.triggers.*.description' => ['nullable', 'string', 'max:2000'],
            'content_blocks.*.data.triggers.*.source_id' => ['nullable', 'integer'],
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
