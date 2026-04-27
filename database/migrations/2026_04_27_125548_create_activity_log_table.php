<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateActivityLogTable extends Migration
{
    public function up()
    {
        // Idempotent — an earlier (since-removed) v5 migration may have already
        // created this table on dev boxes. Skipping when it exists lets the
        // migration row register cleanly instead of erroring on duplicate.
        $connection = Schema::connection(config('activitylog.database_connection'));
        $table = config('activitylog.table_name');

        if ($connection->hasTable($table)) {
            return;
        }

        $connection->create($table, function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('log_name')->nullable();
            $table->text('description');
            $table->nullableMorphs('subject', 'subject');
            $table->nullableMorphs('causer', 'causer');
            $table->json('properties')->nullable();
            $table->timestamps();
            $table->index('log_name');
        });
    }

    public function down()
    {
        Schema::connection(config('activitylog.database_connection'))->dropIfExists(config('activitylog.table_name'));
    }
}
