<?php

use App\Enums\SculptVersionEnum;
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
        Schema::create('packages', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug');
            $table->longText('description')->nullable();
            $table->integer('msrp')->nullable();
            $table->string('sku')->nullable();
            $table->string('upc')->nullable();
            $table->string('distributor_description')->nullable();
            $table->string('front_image')->nullable();
            $table->string('back_image')->nullable();
            $table->string('combination_image')->nullable();
            $table->string('sculpt_version')->default(SculptVersionEnum::FourthEdition->value);
            $table->boolean('is_preassembled')->default(false);
            $table->date('released_at')->nullable();
            $table->timestamps();
        });

        Schema::create('packageables', function (Blueprint $table) {
            $table->morphs('packageable');
            $table->foreignId('package_id')->constrained('packages')->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('packageables');
        Schema::dropIfExists('packages');
    }
};
