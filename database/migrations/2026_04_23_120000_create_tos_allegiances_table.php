<?php

use App\Enums\TOS\AllegianceTypeEnum;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tos_allegiances', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('name');
            $table->string('short_name')->nullable();
            $table->string('type')->default(AllegianceTypeEnum::Earth->value);
            $table->boolean('is_syndicate')->default(false);
            $table->longText('description')->nullable();
            $table->string('logo_path')->nullable();
            $table->string('color_slug')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['type', 'is_syndicate']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tos_allegiances');
    }
};
