<?php

namespace App\Enums;

use App\Interfaces\HasDefaultEnumMethods;
use App\Traits\UsesEnumLabels;
use App\Traits\UsesEnumSelectOptions;

/**
 * Top-level encounter format on a `Game`. Standard = the default Malifaux 4E
 * encounter (50ss, strategy + scheme pool + deployment). BonanzaBrawl = the
 * Wyrd Bonanza Brawl FFA format (11ss single model, no scenario, manual VP).
 * Campaign = an Index-of-the-Untold campaign encounter — uses scenario like
 * Standard but is wrapped by campaign metadata (CR overlay, ss-pool bonus,
 * mandatory Aftermath step on completion).
 *
 * Distinct from `GameModeTypeEnum`, which is used to scope CONTENT (characters,
 * upgrades, etc.) by which mode they're legal in. This enum is per-game state.
 */
enum GameFormatEnum: string implements HasDefaultEnumMethods
{
    use UsesEnumLabels;
    use UsesEnumSelectOptions;

    case Standard = 'standard';
    case BonanzaBrawl = 'bonanza_brawl';
    case Campaign = 'campaign';

    /** Whether this format uses the Strategy / Scheme Pool / Deployment scenario. */
    public function usesScenario(): bool
    {
        return match ($this) {
            self::Standard, self::Campaign => true,
            self::BonanzaBrawl => false,
        };
    }

    /** Whether the SchemeSelect setup phase fires (scenario-less formats skip it). */
    public function usesSchemeSelect(): bool
    {
        return $this->usesScenario();
    }

    /** Whether per-turn strategy/scheme scoring applies. Bonanza is event-driven VP only. */
    public function usesTurnScoring(): bool
    {
        return $this->usesScenario();
    }

    /**
     * Default encounter size in soulstones. Campaign games compute encounter
     * size dynamically from arsenal sizes (min(A,B)+6) — see
     * App\Services\CampaignRules — so the default here is only a safe fallback
     * for forms before arsenal data is available.
     */
    public function defaultEncounterSize(): int
    {
        return match ($this) {
            self::Standard, self::Campaign => 50,
            self::BonanzaBrawl => 11,
        };
    }
}
