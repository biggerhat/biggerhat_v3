<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Public-share support on TOS Companies — mirrors the Malifaux CrewBuild
 * `share_code` + `is_public` pattern. The owner toggles `is_public`; anyone
 * with the share URL gets a read-only view of the Company.
 *
 *  - `share_code` is generated on create via the model's booted hook so the
 *    URL is stable for the lifetime of the row regardless of name changes.
 *  - `is_public` is the access toggle. False (default) = owner-only.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tos_companies', function (Blueprint $table) {
            $table->string('share_code', 24)->nullable()->unique()->after('slug');
            $table->boolean('is_public')->default(false)->after('share_code');
        });
    }

    public function down(): void
    {
        Schema::table('tos_companies', function (Blueprint $table) {
            $table->dropUnique(['share_code']);
            $table->dropColumn(['share_code', 'is_public']);
        });
    }
};
