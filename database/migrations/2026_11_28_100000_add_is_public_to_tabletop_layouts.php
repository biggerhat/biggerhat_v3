<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A saved table can be listed in the public gallery as well as shared by link (like a crew in the crew builder). Public implies shared.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tabletop_layouts', function (Blueprint $table) {
            $table->boolean('is_public')->default(false)->after('is_shared');
            $table->index(['is_public', 'updated_at'], 'tabletop_layouts_public_idx');
        });
    }

    public function down(): void
    {
        Schema::table('tabletop_layouts', function (Blueprint $table) {
            $table->dropIndex('tabletop_layouts_public_idx');
            $table->dropColumn('is_public');
        });
    }
};
