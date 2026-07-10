<?php

namespace App\Models\Campaign;

use Database\Factories\Campaign\CampaignAftermathFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One Aftermath flow per crew per game. The fate-deck-no-reshuffle invariant
 * is preserved by snapshotting the drawn hand at Phase 1 start and persisting
 * `hand_drawn` server-side — refreshing the wizard mid-flow resumes on the
 * same hand.
 *
 * `hand_drawn` is the immutable snapshot of cards drawn in Phase 1:
 *     [{ value: int, suit: string, is_joker: bool }, ...]
 *
 * `hand_used` is an append-only audit log of phase events for the history
 * panel — currently written by the skip-phase advance() endpoint:
 *     [{ phase: int, used_for: string, notes: string, at: ISO8601 }, ...]
 *
 * @property int $id
 * @property int $campaign_game_id
 * @property int $campaign_crew_id
 * @property int $current_phase
 * @property array<int, array{value: int, suit: string, is_joker: bool}>|null $hand_drawn
 * @property array<int, array{phase: int, used_for: string, notes: string, at: string}>|null $hand_used
 * @property int $scrip_earned
 * @property string $status
 * @property string|null $story_entry
 * @property-read CampaignGame $campaignGame
 * @property-read CampaignCrew $crew
 *
 * @mixin IdeHelperCampaignAftermath
 */
class CampaignAftermath extends Model
{
    /** @use HasFactory<CampaignAftermathFactory> */
    use HasFactory;

    protected $guarded = ['id'];

    public function casts(): array
    {
        return [
            'hand_drawn' => 'array',
            'hand_used' => 'array',
        ];
    }

    protected static function newFactory(): CampaignAftermathFactory
    {
        return CampaignAftermathFactory::new();
    }

    public function campaignGame(): BelongsTo
    {
        return $this->belongsTo(CampaignGame::class);
    }

    public function crew(): BelongsTo
    {
        return $this->belongsTo(CampaignCrew::class, 'campaign_crew_id');
    }
}
