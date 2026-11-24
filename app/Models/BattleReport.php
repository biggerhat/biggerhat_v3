<?php

namespace App\Models;

use App\Enums\BattleReportStatusEnum;
use App\Tournaments\RichText;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * A player's written account of a game: a rich-text document, optionally tied to a game they played (tracked or logged).
 * Private until the author makes it public.
 *
 * @mixin IdeHelperBattleReport
 */
class BattleReport extends Model
{
    /** @use HasFactory<\Database\Factories\BattleReportFactory> */
    use HasFactory;

    protected $guarded = ['id'];

    protected static function booted(): void
    {
        static::creating(function (self $report) {
            $report->uuid ??= (string) Str::uuid();
        });
    }

    protected function casts(): array
    {
        return [
            'content' => 'array',
            'show_game' => 'boolean',
            'allow_comments' => 'boolean',
            'status' => BattleReportStatusEnum::class,
            'published_at' => 'datetime',
            'moderated_at' => 'datetime',
            'approved_snapshot' => 'array',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    /** @return BelongsTo<User, $this> */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /** @return BelongsTo<User, $this> */
    public function moderator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'moderated_by');
    }

    /** @return BelongsTo<TournamentGame, $this> */
    public function tournamentGame(): BelongsTo
    {
        return $this->belongsTo(TournamentGame::class);
    }

    /** What the report is about, as one short key ("g12" a game, "t5" a tournament round), for comparing against what a moderator read. */
    public function linkKey(): ?string
    {
        return $this->game_id ? 'g'.$this->game_id : ($this->tournament_game_id ? 't'.$this->tournament_game_id : null);
    }

    /** @return BelongsTo<CrewBuild, $this> */
    public function crew(): BelongsTo
    {
        return $this->belongsTo(CrewBuild::class, 'crew_build_id');
    }

    /** @return BelongsTo<Game, $this> */
    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }

    /** @return HasMany<BattleReportComment, $this> */
    public function comments(): HasMany
    {
        return $this->hasMany(BattleReportComment::class);
    }

    /** @return HasMany<BattleReportReaction, $this> */
    public function reactions(): HasMany
    {
        return $this->hasMany(BattleReportReaction::class);
    }

    /** @return HasMany<BattleReportFlag, $this> */
    public function flags(): HasMany
    {
        return $this->hasMany(BattleReportFlag::class);
    }

    /** @param  Builder<BattleReport>  $query */
    public function scopePublic(Builder $query): Builder
    {
        return $query->where('status', BattleReportStatusEnum::Approved->value)->whereNotNull('published_at');
    }

    /** Shown to anyone: a moderator approved it. */
    public function isPublic(): bool
    {
        return $this->status === BattleReportStatusEnum::Approved && $this->published_at !== null;
    }

    /** The writer wants it public (asked and waiting, or approved). */
    public function wantsPublic(): bool
    {
        return in_array($this->status, [BattleReportStatusEnum::Pending, BattleReportStatusEnum::Approved], true);
    }

    /** The first picture in the text, for the preview a shared link shows (null if there is none). */
    public function firstImage(): ?string
    {
        foreach (RichText::media($this->content) as $src) {
            if (RichText::isOwnImage($src)) {
                return $src;
            }
        }

        return null;
    }

    public function excerpt(int $length = 200): string
    {
        return Str::limit(RichText::plainText($this->content), $length);
    }
}
