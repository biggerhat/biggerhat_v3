<?php

use App\Enums\ActionTypeEnum;
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
        Schema::create('actions', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug');
            $table->string('type')->default(ActionTypeEnum::Attack->value);
            $table->boolean('is_signature')->default(false);
            $table->string('cost')->nullable();
            $table->integer('range')->nullable();
            $table->string('range_type')->nullable();
            $table->integer('stat')->nullable();
            $table->string('stat_suits')->nullable();
            $table->string('stat_modifier')->nullable();
            $table->string('resisted_by')->nullable();
            $table->integer('target_number')->nullable();
            $table->string('target_suits')->nullable();
            $table->longText('description')->nullable();
            $table->integer('damage')->nullable();
            $table->longText('internal_notes')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('actions');
    }
};
