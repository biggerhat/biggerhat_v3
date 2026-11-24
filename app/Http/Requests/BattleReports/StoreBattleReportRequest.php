<?php

namespace App\Http\Requests\BattleReports;

use App\Enums\GameStatusEnum;
use App\Models\BattleReport;
use App\Models\Game;
use App\Models\TournamentGame;
use App\Support\Game\LinkableRounds;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreBattleReportRequest extends FormRequest
{
    /** The longest a report's document may be once written out (bytes of JSON). Images are files, not part of this. */
    public const MAX_DOCUMENT_BYTES = 200_000;

    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, array<int, mixed>|string> */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:160'],
            'content' => ['nullable', 'array'],
            'is_public' => ['sometimes', 'boolean'],
            'show_game' => ['sometimes', 'boolean'],
            'allow_comments' => ['sometimes', 'boolean'],
            'game_uuid' => ['nullable', 'uuid'],
            // Or a tournament round the writer played that was not tracked (not both).
            'round_id' => ['nullable', 'integer'],
            // One of the writer's own crew lists (not deleted).
            'crew_id' => ['nullable', 'integer', Rule::exists('crew_builds', 'id')->where('user_id', $this->user()?->id)->whereNull('deleted_at')],
        ];
    }

    /** @return array<int, callable(Validator): void> */
    public function after(): array
    {
        return [function (Validator $validator) {
            if (strlen((string) json_encode($this->input('content'))) > self::MAX_DOCUMENT_BYTES) {
                $validator->errors()->add('content', 'This report is too long. Shorten it or split it in two.');
            }
            if ($this->input('round_id') && $this->input('game_uuid')) {
                $validator->errors()->add('game_uuid', 'A report is about a game or a tournament round, not both.');

                return;
            }
            if ($roundId = $this->input('round_id')) {
                if ($this->round() === null) {
                    $validator->errors()->add('round_id', 'Pick a round of a finished tournament you played in.');
                } elseif (BattleReport::where('user_id', $this->user()->id)->where('tournament_game_id', (int) $roundId)
                    ->when($this->currentReport(), fn ($q, BattleReport $r) => $q->whereKeyNot($r->id))->exists()) {
                    $validator->errors()->add('round_id', 'You already wrote a report for that round.');
                }

                return;
            }
            $uuid = $this->input('game_uuid');
            if (! $uuid) {
                return;
            }
            $game = $this->linkableGame((string) $uuid);
            if ($game === null) {
                $validator->errors()->add('game_uuid', 'Pick one of your finished games.');

                return;
            }
            $taken = BattleReport::where('user_id', $this->user()->id)->where('game_id', $game->id)
                ->when($this->currentReport(), fn ($q, BattleReport $r) => $q->whereKeyNot($r->id))
                ->exists();
            if ($taken) {
                $validator->errors()->add('game_uuid', 'You already wrote a report for that game.');
            }
        }];
    }

    /** The game the report is about, when it is a finished game the writer played in. */
    public function game(): ?Game
    {
        $uuid = $this->validated('game_uuid');

        return $uuid ? $this->linkableGame((string) $uuid) : null;
    }

    /** The tournament round the report is about, when it is one the writer played (validated). */
    public function round(): ?TournamentGame
    {
        $id = $this->input('round_id');

        return $id ? LinkableRounds::find($this->user(), (int) $id) : null;
    }

    protected function currentReport(): ?BattleReport
    {
        return null;
    }

    private function linkableGame(string $uuid): ?Game
    {
        return Game::forUser($this->user()->id)->where('status', GameStatusEnum::Completed->value)->where('uuid', $uuid)->first();
    }
}
