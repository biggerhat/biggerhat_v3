<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * ResistanceTypeEnum::Move's abbreviation was wrong — Malifaux card text
 * uses "Sp" for this resistance type, not "Mv" — corrected in the enum
 * itself. Backfills every place that value was already baked in as literal
 * stored text (not a live join to actions.resisted_by, so fixing the enum
 * alone wouldn't reach these):
 *
 * - actions.resisted_by — plain string column, the source of truth.
 * - custom_characters.actions — JSON array of per-action maps, each
 *   snapshotted from an Action row at some past build/save time (Leader
 *   Builder, Tier-4 Attack/Tactical Mod application, etc.).
 * - advancement_actions.stat_block — JSON object, only for bespoke,
 *   admin-authored rows with no linked action_id (a row with action_id set
 *   always reads resisted_by live from the actions table, so it's already
 *   covered by the first update).
 *
 * Nothing else persists a copy — every other spot that surfaces
 * resisted_by (API resources, AftermathCatalog, campaign controllers) reads
 * live from one of these three at request time.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('actions')->where('resisted_by', 'Mv')->update(['resisted_by' => 'Sp']);

        $this->renameInStatBlock('Mv', 'Sp');
        $this->renameInCustomCharacterActions('Mv', 'Sp');
    }

    public function down(): void
    {
        DB::table('actions')->where('resisted_by', 'Sp')->update(['resisted_by' => 'Mv']);

        $this->renameInStatBlock('Sp', 'Mv');
        $this->renameInCustomCharacterActions('Sp', 'Mv');
    }

    private function renameInStatBlock(string $from, string $to): void
    {
        // get() (not cursor()) — writing rows back into the same table while
        // a streaming cursor SELECT is still open against it is unsafe
        // (silently no-ops on SQLite; unreliable on MySQL too).
        DB::table('advancement_actions')
            ->whereNull('action_id')
            ->whereNotNull('stat_block')
            ->select('id', 'stat_block')
            ->get()
            ->each(function ($row) use ($from, $to) {
                $statBlock = json_decode($row->stat_block, true);
                if (! is_array($statBlock) || ($statBlock['resisted_by'] ?? null) !== $from) {
                    return;
                }
                $statBlock['resisted_by'] = $to;
                DB::table('advancement_actions')->where('id', $row->id)->update(['stat_block' => json_encode($statBlock)]);
            });
    }

    private function renameInCustomCharacterActions(string $from, string $to): void
    {
        DB::table('custom_characters')
            ->whereNotNull('actions')
            ->select('id', 'actions')
            ->get()
            ->each(function ($row) use ($from, $to) {
                $actions = json_decode($row->actions, true);
                if (! is_array($actions)) {
                    return;
                }
                $changed = false;
                foreach ($actions as &$action) {
                    if (is_array($action) && ($action['resisted_by'] ?? null) === $from) {
                        $action['resisted_by'] = $to;
                        $changed = true;
                    }
                }
                unset($action);
                if ($changed) {
                    DB::table('custom_characters')->where('id', $row->id)->update(['actions' => json_encode($actions)]);
                }
            });
    }
};
