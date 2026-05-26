<?php

namespace App\Models\Campaign;

use App\Models\CustomCharacter;
use Database\Factories\Campaign\CampaignLeaderXpTrackFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The 27-box Leadership Experience chart per pg 31 (13 + 7 + 7). Each row in
 * the JSON `track` carries `{ index, filled, tier }` so the renderer can map
 * directly to the printed chart and the advance flow can find the next
 * unfilled box.
 *
 * Tier numbers per the rulebook chart:
 *   Row 1 (13 boxes): 1, 1, 2, _, 3, _, 4, _, 1, _, 2, _, 4
 *   Row 2 (7 boxes):  _, _, 1, _, 2, 1, _
 *   Row 3 (7 boxes):  _, _, _, 1, _, 2, 4
 *
 * A `null` tier means "earn an XP point but no advancement triggered at this
 * position." Numbered boxes trigger a Tier-X advancement spend.
 *
 * @property int $id
 * @property int $custom_character_id
 * @property array<int, array{index: int, filled: bool, tier: int|null}> $track
 * @property-read CustomCharacter $leader
 */
class CampaignLeaderXpTrack extends Model
{
    /** @use HasFactory<CampaignLeaderXpTrackFactory> */
    use HasFactory;

    protected $guarded = ['id'];

    public function casts(): array
    {
        return [
            'track' => 'array',
        ];
    }

    protected static function newFactory(): CampaignLeaderXpTrackFactory
    {
        return CampaignLeaderXpTrackFactory::new();
    }

    public function leader(): BelongsTo
    {
        return $this->belongsTo(CustomCharacter::class, 'custom_character_id');
    }

    /**
     * The canonical chart layout (pg 31). Used to initialize a fresh track
     * when a leader is created.
     *
     * @return array<int, array{index: int, filled: bool, tier: int|null}>
     */
    public static function defaultTrack(): array
    {
        $row1 = [1, 1, 2, null, 3, null, 4, null, 1, null, 2, null, 4];
        $row2 = [null, null, 1, null, 2, 1, null];
        $row3 = [null, null, null, 1, null, 2, 4];

        $track = [];
        $index = 0;
        foreach (array_merge($row1, $row2, $row3) as $tier) {
            $track[] = ['index' => $index, 'filled' => false, 'tier' => $tier];
            $index++;
        }

        return $track;
    }
}
