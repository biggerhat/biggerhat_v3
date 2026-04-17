<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Public feedback inbox — replaces the publicly-exposed contact email on the
 * Privacy page. Anyone (logged in or anonymous) can submit; admins review in
 * the Admin › Feedback index.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('feedback', function (Blueprint $table) {
            $table->id();
            // Null for anonymous submissions. On user delete, keep the row
            // but drop the association — feedback content remains useful.
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('name')->nullable();
            $table->string('email')->nullable();
            $table->string('category')->default('general');
            $table->string('subject')->nullable();
            $table->text('message');
            // Referring URL the user was on when submitting — helps admins
            // reproduce bugs and contextualise feature requests.
            $table->string('url', 2048)->nullable();
            $table->string('status')->default('new');
            $table->text('admin_notes')->nullable();
            $table->string('submitter_ip', 45)->nullable();
            $table->timestamps();

            $table->index('status');
            $table->index('category');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('feedback');
    }
};
