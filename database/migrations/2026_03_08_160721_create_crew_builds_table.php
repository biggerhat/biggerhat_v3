<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crew_builds', function (Blueprint $table) {
            $table->id();
            $table->string('name')->default('Untitled Crew');
            $table->string('share_code', 12)->unique();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('faction');
            $table->foreignId('master_id')->constrained('characters')->cascadeOnDelete();
            $table->unsignedInteger('encounter_size')->default(50);
            $table->json('crew_data');
            $table->boolean('is_archived')->default(false);
            $table->timestamps();

            $table->index('user_id');
            $table->index('share_code');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crew_builds');
    }
};
