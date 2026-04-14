<?php

namespace App\Http\Controllers;

use App\Enums\ActionRangeTypeEnum;
use App\Enums\ActionTypeEnum;
use App\Enums\FactionEnum;
use App\Enums\SuitEnum;
use App\Enums\UpgradeLimitationEnum;
use App\Enums\UpgradeTypeEnum;
use App\Models\CustomUpgrade;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Response;

class CustomUpgradeController extends Controller
{
    public function create(Request $request): Response
    {
        $domain = $request->query('domain', 'character');
        if (! in_array($domain, ['character', 'crew'])) {
            $domain = 'character';
        }

        return inertia('Tools/CardCreator/UpgradeEditor', [
            'upgrade' => null,
            'domain' => $domain,
            'enums' => $this->enumOptions(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $this->validateUpgrade($request);
        $validated['user_id'] = Auth::id();

        $upgrade = CustomUpgrade::create($validated);

        return response()->json([
            'success' => true,
            'redirect' => route('tools.card_creator.upgrades.edit', $upgrade->id),
        ]);
    }

    public function edit(CustomUpgrade $customUpgrade): Response
    {
        if ($customUpgrade->user_id !== Auth::id()) {
            abort(403);
        }

        return inertia('Tools/CardCreator/UpgradeEditor', [
            'upgrade' => $customUpgrade,
            'domain' => $customUpgrade->domain instanceof \App\Enums\UpgradeDomainTypeEnum ? $customUpgrade->domain->value : $customUpgrade->domain,
            'enums' => $this->enumOptions(),
        ]);
    }

    public function update(Request $request, CustomUpgrade $customUpgrade): JsonResponse
    {
        if ($customUpgrade->user_id !== Auth::id()) {
            abort(403);
        }

        $validated = $this->validateUpgrade($request);

        $customUpgrade->update($validated);

        return response()->json([
            'success' => true,
        ]);
    }

    public function destroy(CustomUpgrade $customUpgrade): JsonResponse
    {
        if ($customUpgrade->user_id !== Auth::id()) {
            abort(403);
        }

        $customUpgrade->delete();

        return response()->json(['success' => true]);
    }

    public function share(string $shareCode): Response
    {
        $upgrade = CustomUpgrade::where('share_code', $shareCode)
            ->with('user:id,name')
            ->firstOrFail();

        /** @var \App\Models\User $user */
        $user = $upgrade->user;

        return inertia('Tools/CardCreator/UpgradeView', [
            'upgrade' => $upgrade,
            'creator_name' => $user->name,
        ]);
    }

    private function validateUpgrade(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'domain' => ['required', 'string', 'in:crew,character'],
            'type' => ['nullable', 'string'],
            'faction' => ['nullable', 'string'],
            'limitations' => ['nullable', 'string'],
            'plentiful' => ['nullable', 'integer', 'min:1', 'max:10'],
            'master_name' => ['nullable', 'string', 'max:255'],
            'keyword_name' => ['nullable', 'string', 'max:255'],
            'content_blocks' => ['nullable', 'array'],
            'content_blocks.*.type' => ['required', 'string', 'in:text,ability,action,trigger'],
            'content_blocks.*.text' => ['nullable', 'string', 'max:1000'],
            'content_blocks.*.data' => ['nullable', 'array'],
            'back_tokens' => ['nullable', 'array'],
            'back_tokens.*.name' => ['required', 'string', 'max:255'],
            'back_tokens.*.description' => ['nullable', 'string'],
            'back_tokens.*.source_id' => ['nullable', 'integer'],
            'back_markers' => ['nullable', 'array'],
            'back_markers.*.name' => ['required', 'string', 'max:255'],
            'back_markers.*.description' => ['nullable', 'string'],
            'back_markers.*.source_id' => ['nullable', 'integer'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);
    }

    private function enumOptions(): array
    {
        return [
            'factions' => FactionEnum::toSelectOptions(),
            'upgrade_types' => UpgradeTypeEnum::toSelectOptions(),
            'limitations' => UpgradeLimitationEnum::toSelectOptions(),
            'suits' => SuitEnum::toSelectOptions(),
            'action_types' => ActionTypeEnum::toSelectOptions(),
            'range_types' => ActionRangeTypeEnum::toSelectOptions(),
        ];
    }
}
