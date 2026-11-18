<?php

namespace App\Support\Fate;

use App\Models\Game;
use App\Models\GameLog;
use App\Models\GamePlayer;

/**
 * The Fate history as readable lines, for the table and for anyone watching. The action log it reads never holds a hidden card (only
 * counts), so every line here is safe to show to everyone: a flip's revealed cards, a discard and a forced discard are face up; a draw,
 * a look at the top or a shown hand is only ever "N cards" or "showed a hand".
 */
final class FateLog
{
    /**
     * @return list<array{id: int, at: string, text: string}> newest first
     */
    public static function lines(Game $game, int $limit = 60): array
    {
        $names = $game->players()->with('user:id,name')->get()->mapWithKeys(fn (GamePlayer $p) => [
            $p->id => ($p->user_id !== null ? $p->user->name : null) ?? $p->opponent_name ?? 'Player '.$p->slot,
        ]);

        return GameLog::query()
            ->where('game_id', $game->id)
            ->where('action', 'like', 'fate.%')
            ->orderByDesc('id')
            ->limit($limit)
            ->get()
            ->map(fn (GameLog $log) => [
                'id' => $log->id,
                'at' => $log->created_at->toIso8601String(),
                'text' => self::describe($log->action, $log->payload ?? [], (string) ($names[$log->game_player_id] ?? 'Someone'), $names->all()),
            ])
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $p
     * @param  array<int, string>  $names
     */
    public static function describe(string $action, array $p, string $who, array $names = []): string
    {
        $cards = fn (?array $list) => $list ? implode(', ', array_map(fn ($c) => FateCards::label((int) $c), $list)) : 'nothing';
        $n = fn (int $count, string $word = 'card') => $count.' '.$word.($count === 1 ? '' : 's');
        $mod = fn (int $m) => $m > 0 ? "+{$m}" : ($m < 0 ? (string) $m : 'a plain flip');

        return match ($action) {
            'fate.draw' => "{$who} drew ".$n((int) ($p['count'] ?? 0)).'.',
            'fate.refill' => "{$who} discarded ".$cards($p['discarded'] ?? []).' and drew up to the hand limit ('.$n((int) ($p['drawn'] ?? 0)).' drawn).',
            'fate.discard' => "{$who} discarded ".$cards($p['cards'] ?? []).'.',
            'fate.flip' => "{$who} flipped ".$mod((int) ($p['modifier'] ?? 0)).': '.$cards($p['revealed'] ?? []).'.',
            'fate.choose' => "{$who} took ".$cards([(int) ($p['card'] ?? 0)]).(! empty($p['suit']) ? " as {$p['suit']}" : '').' as the result.',
            'fate.cheat' => "{$who} cheated Fate: ".$cards([(int) ($p['replaced'] ?? 0)]).' replaced by '.$cards([(int) ($p['with'] ?? 0)]).(! empty($p['suit']) ? " as {$p['suit']}" : '').'.',
            'fate.cheat_declined' => "{$who} passed on cheating.",
            'fate.resolve' => "{$who} resolved the flip: ".$cards($p['cards'] ?? []).' discarded.',
            'fate.shuffle' => "{$who} shuffled their discard pile into their deck.",
            'fate.end_phase' => 'End phase: each discard pile was shuffled into its deck.',
            'fate.remove' => "{$who} set aside ".$cards($p['cards'] ?? []).', removed from the game.',
            'fate.peek' => "{$who} looked at ".$n((int) ($p['count'] ?? 0)).' on top of their deck.',
            'fate.arrange' => "{$who} rearranged the top ".$n((int) ($p['looked_at'] ?? 0)).' of their deck'.(! empty($p['discarded']) ? ' (discarded '.$cards($p['discarded']).')' : '').'.',
            'fate.put_on_deck' => "{$who} put ".(isset($p['card']) ? $cards([(int) $p['card']]) : 'a card from their hand').' on the '.($p['where'] ?? 'top').' of their deck.',
            'fate.draw_from_discard' => "{$who} took ".$cards([(int) ($p['card'] ?? 0)]).' back from their discard pile.',
            'fate.search' => "{$who} looked through their deck.",
            'fate.search_take' => "{$who} took a card from their deck and shuffled it.",
            'fate.demand' => ($p['kind'] ?? '') === 'reveal'
                ? "{$who} asked ".($names[(int) ($p['to'] ?? 0)] ?? 'the other player').' to show their hand'.(! empty($p['note']) ? " ({$p['note']})" : '').'.'
                : "{$who} asked ".($names[(int) ($p['to'] ?? 0)] ?? 'the other player').' to discard '.$n((int) ($p['count'] ?? 0)).(! empty($p['note']) ? " ({$p['note']})" : '').'.',
            'fate.reveal' => "{$who} showed their hand to ".($names[(int) ($p['to'] ?? 0)] ?? 'the other player').'.',
            'fate.reveal_declined' => "{$who} declined to show their hand.",
            'fate.discard_done' => "{$who} finished discarding ".$n((int) ($p['count'] ?? 0)).'.',
            'fate.forced_discard' => ($names[(int) ($p['target'] ?? 0)] ?? 'The other player').' discarded '.$cards($p['cards'] ?? [])." at random, because of {$who}.",
            default => "{$who}: {$action}.",
        };
    }
}
