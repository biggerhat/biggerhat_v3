<?php

use App\Enums\TOS\AssetLimitTypeEnum;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tos_asset_limits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('asset_id')->constrained('tos_assets')->cascadeOnDelete();
            $table->string('limit_type')->default(AssetLimitTypeEnum::Unique->value);
            $table->string('parameter_type')->nullable();
            // String payload — `parameter_value` is authoritative for free-form
            // limits (Slot location, Adjunct size) and for Restricted-by-name
            // where a unit may not exist in the DB yet. When Restricted-by-
            // Unit-Name or Restricted-by-Allegiance DO resolve to a record,
            // the optional FK columns below capture referential integrity too.
            $table->string('parameter_value')->nullable();
            $table->foreignId('parameter_unit_id')->nullable()->constrained('tos_units')->nullOnDelete();
            $table->foreignId('parameter_allegiance_id')->nullable()->constrained('tos_allegiances')->nullOnDelete();
            $table->string('notes')->nullable();
            $table->timestamps();

            $table->index(['asset_id', 'limit_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tos_asset_limits');
    }
};
