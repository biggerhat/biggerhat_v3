<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pod_links', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('source'); // PodSourceEnum
            $table->string('url');
            $table->timestamps();
        });

        Schema::create('pod_link_taggables', function (Blueprint $table) {
            $table->foreignId('pod_link_id')->constrained('pod_links')->cascadeOnDelete();
            $table->morphs('taggable');
        });

        Schema::create('pod_link_faction', function (Blueprint $table) {
            $table->foreignId('pod_link_id')->constrained('pod_links')->cascadeOnDelete();
            $table->string('faction');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pod_link_faction');
        Schema::dropIfExists('pod_link_taggables');
        Schema::dropIfExists('pod_links');
    }
};
