<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wishlists', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name')->default('My Wishlist');
            $table->string('share_code', 12)->unique();
            $table->boolean('is_public')->default(false);
            $table->timestamps();

            $table->index('user_id');
        });

        Schema::create('wishlist_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('wishlist_id')->constrained()->cascadeOnDelete();
            $table->morphs('wishlistable');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['wishlist_id', 'wishlistable_type', 'wishlistable_id'], 'wishlist_items_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wishlist_items');
        Schema::dropIfExists('wishlists');
    }
};
