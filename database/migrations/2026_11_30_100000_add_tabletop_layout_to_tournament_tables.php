<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tournament_tables', function (Blueprint $table) {
            $table->foreignId('tabletop_layout_id')->nullable()->after('image_path')->constrained('tabletop_layouts', indexName: 'tt_tables_layout_fk')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('tournament_tables', function (Blueprint $table) {
            $table->dropForeign('tt_tables_layout_fk');
            $table->dropColumn('tabletop_layout_id');
        });
    }
};
