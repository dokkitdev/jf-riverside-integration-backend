<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

class JobsTableAddRequestLogIdField extends Migration
{
    public function up()
    {
        Schema::table('jobs', function (Blueprint $table) {
            $table
                ->foreignId('request_log_id')
                ->nullable()
                ->constrained('request_logs')
                ->nullOnDelete()
                ->cascadeOnUpdate();
        });
    }

    public function down()
    {
        Schema::table('jobs', function (Blueprint $table) {
            $table->dropConstrainedForeignId('request_log_id');
        });
    }
}
