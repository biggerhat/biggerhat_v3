<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Titled models (pg 18): hiring one titled version of a character adds
     * every other titled version to the arsenal too, sharing injuries. This
     * column is the catalog-side grouping key (admin-fillable) — characters
     * sharing a non-null title_group_key are siblings. Unpopulated until a
     * follow-up admin data-entry pass; the hire-flow mechanism that reads it
     * ships now regardless.
     */
    public function up(): void
    {
        Schema::table('characters', function (Blueprint $table) {
            $table->string('title_group_key')->nullable()->after('title');
            $table->index('title_group_key');
        });
    }

    public function down(): void
    {
        Schema::table('characters', function (Blueprint $table) {
            $table->dropIndex(['title_group_key']);
            $table->dropColumn('title_group_key');
        });
    }
};
