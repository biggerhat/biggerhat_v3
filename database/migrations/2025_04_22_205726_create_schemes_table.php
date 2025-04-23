<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('schemes', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug');
            $table->string('season');
            $table->string('selector')->nullable();
            $table->longText('prerequisite')->nullable();
            $table->longText('reveal');
            $table->longText('scoring');
            $table->longText('additional');
            $table->foreignId('next_scheme_one_id')->nullable()->constrained('schemes')->cascadeOnDelete();
            $table->foreignId('next_scheme_two_id')->nullable()->constrained('schemes')->cascadeOnDelete();
            $table->foreignId('next_scheme_three_id')->nullable()->constrained('schemes')->cascadeOnDelete();
            $table->string('image')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('schemes');
    }
};
