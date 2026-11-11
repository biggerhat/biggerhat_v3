<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tournaments', function (Blueprint $table) {
            // The long-form event page (TipTap JSON, sanitised on the way in). `description` stays the one-line blurb.
            $table->json('details')->nullable();
        });

        Schema::create('tournament_tables', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tournament_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('number');
            $table->string('name', 80)->nullable();
            $table->text('description')->nullable();
            $table->string('image_path')->nullable();
            $table->timestamps();

            $table->unique(['tournament_id', 'number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tournament_tables');

        Schema::table('tournaments', function (Blueprint $table) {
            $table->dropColumn('details');
        });
    }
};
