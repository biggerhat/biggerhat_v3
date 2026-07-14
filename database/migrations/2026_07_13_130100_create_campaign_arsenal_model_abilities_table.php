<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Abilities a Campaign hired model has permanently gained outside its
     * base Character catalog row — currently only via a Lucky Miss result
     * (pg 36) that names a real Ability. Additive: the model's base
     * Character abilities are untouched, this is purely extra.
     *
     * Explicit short FK constraint names: the table name alone is 33 chars,
     * so Laravel's auto-generated name for the campaign_arsenal_model_id FK
     * ("campaign_arsenal_model_abilities_campaign_arsenal_model_id_foreign",
     * 65 chars) exceeds MySQL's 64-char identifier limit — this is exactly
     * what failed the first production deploy (table got created, the FK
     * ALTER after it didn't). hasTable()/tryAddForeign() below make this
     * safe to rerun against that exact partial state as well as a clean one.
     */
    public function up(): void
    {
        if (! Schema::hasTable('campaign_arsenal_model_abilities')) {
            Schema::create('campaign_arsenal_model_abilities', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('campaign_arsenal_model_id');
                $table->unsignedBigInteger('ability_id');
                $table->string('source')->default('lucky_miss');
                $table->timestamps();
            });
        }

        $this->tryAddForeign('campaign_arsenal_model_abilities', function (Blueprint $table) {
            $table->foreign('campaign_arsenal_model_id', 'cama_arsenal_model_id_fk')
                ->references('id')->on('campaign_arsenal_models')->cascadeOnDelete();
        });

        $this->tryAddForeign('campaign_arsenal_model_abilities', function (Blueprint $table) {
            $table->foreign('ability_id', 'cama_ability_id_fk')
                ->references('id')->on('abilities')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('campaign_arsenal_model_abilities');
    }

    private function tryAddForeign(string $table, \Closure $add): void
    {
        try {
            Schema::table($table, $add);
        } catch (\Throwable $e) {
            // Constraint already exists — fine, the migration is rerunnable.
        }
    }
};
