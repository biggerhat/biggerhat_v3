<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('announcements', function (Blueprint $table) {
            $table->id();
            // Compact body for the sticky banner. Markdown allowed; rendered
            // as plain text in the banner UI for safety, formatted on a future
            // dedicated page if we want it.
            $table->string('message');
            // 'info' | 'warning' | 'success' — drives the banner color.
            $table->string('level')->default('info');
            // Audience filter:
            //   - 'all' shows to everyone (incl. logged-out)
            //   - 'authenticated' shows only to logged-in users
            //   - 'super_admin' shows only to super_admins
            $table->string('audience')->default('all');
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->boolean('is_dismissable')->default(true);
            // Locally-scoped link the banner can render. Optional.
            $table->string('link_url')->nullable();
            $table->string('link_label')->nullable();
            $table->foreignId('created_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['starts_at', 'ends_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('announcements');
    }
};
