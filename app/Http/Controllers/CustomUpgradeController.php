<?php

namespace App\Http\Controllers;

use App\Enums\ActionRangeTypeEnum;
use App\Enums\ActionTypeEnum;
use App\Enums\FactionEnum;
use App\Enums\SuitEnum;
use App\Enums\UpgradeLimitationEnum;
use App\Enums\UpgradeTypeEnum;
use App\Http\Requests\CustomUpgradeRequest;
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

    public function store(CustomUpgradeRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $validated['user_id'] = Auth::id();

        $upgrade = CustomUpgrade::create($validated);

        return response()->json([
            'success' => true,
            'redirect' => route('tools.card_creator.upgrades.edit', $upgrade->id),
        ]);
    }

    public function edit(CustomUpgrade $customUpgrade): Response
    {
        $this->authorize('update', $customUpgrade);

        return inertia('Tools/CardCreator/UpgradeEditor', [
            'upgrade' => $customUpgrade,
            'domain' => $customUpgrade->domain instanceof \App\Enums\UpgradeDomainTypeEnum ? $customUpgrade->domain->value : $customUpgrade->domain,
            'enums' => $this->enumOptions(),
        ]);
    }

    public function update(CustomUpgradeRequest $request, CustomUpgrade $customUpgrade): JsonResponse
    {
        $validated = $request->validated();

        $customUpgrade->update($validated);

        return response()->json([
            'success' => true,
        ]);
    }

    public function destroy(CustomUpgrade $customUpgrade): JsonResponse
    {
        $this->authorize('delete', $customUpgrade);

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
