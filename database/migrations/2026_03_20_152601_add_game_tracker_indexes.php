<?php

use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        // Indexes already exist via foreign key constraints on MySQL
    }

    public function down(): void
    {
        //
    }
};
