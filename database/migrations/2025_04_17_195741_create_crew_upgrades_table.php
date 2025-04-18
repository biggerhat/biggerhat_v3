<?php

use App\Enums\UpgradeTypeEnum;
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
        Schema::create('upgrades', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug');
            $table->string('type')->default(UpgradeTypeEnum::Crew->value);
            $table->longText('description')->nullable();
            $table->foreignId('master_id')->constrained('characters');
            $table->integer('power_bar_count')->nullable();
            $table->string('front_image')->nullable();
            $table->string('back_image')->nullable();
            $table->string('combination_image')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('upgradeables', function (Blueprint $table) {
            $table->morphs('upgradeable', 'upgradeables_index_unique');
            $table->foreignId('upgrade_id')->constrained('upgrades')->cascadeOnDelete();
        });

        Schema::table('characters', function (Blueprint $table) {
            $table->after('has_totem_id', function ($table) {
                $table->foreignId('crew_upgrade_id')->nullable()->constrained('upgrades')->cascadeOnDelete();
            });
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('upgradeables');
        Schema::dropIfExists('upgrades');
    }
};
