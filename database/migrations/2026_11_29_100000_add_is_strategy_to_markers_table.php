<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Which markers are Strategy markers (Comprehensive Rules Guide, p.46): neutral to every crew, never counted against the five-a-kind limit,
 * and a model may move through one but not end a move on it. The tabletop treats them that way.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('markers', function (Blueprint $table) {
            $table->boolean('is_strategy')->default(false)->after('base');
        });

        // A starting point: markers whose name says so. Admins can correct any of them in the marker form.
        DB::table('markers')->whereRaw('LOWER(name) LIKE ?', ['%strategy%'])->update(['is_strategy' => true]);
    }

    public function down(): void
    {
        Schema::table('markers', function (Blueprint $table) {
            $table->dropColumn('is_strategy');
        });
    }
};
