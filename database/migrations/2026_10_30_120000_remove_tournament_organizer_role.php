<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Anyone signed in can now run a tournament, so the `create_tournaments` permission and the
 * `tournament_organizer` role that only granted it no longer mean anything. Per-event control
 * still comes from the tournament's creator / organizers pivot.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('roles') || ! Schema::hasTable('permissions')) {
            return;
        }

        // Role/permission pivots cascade on delete, so users simply lose the (now empty) role.
        DB::table('roles')->where('name', 'tournament_organizer')->delete();
        DB::table('permissions')->where('name', 'create_tournaments')->delete();
    }

    public function down(): void
    {
        // Nothing to restore: the role/permission are intentionally gone.
    }
};
