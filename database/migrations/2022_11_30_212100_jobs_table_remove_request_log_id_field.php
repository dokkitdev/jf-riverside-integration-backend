<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;

class JobsTableRemoveRequestLogIdField extends Migration
{
    public function up()
    {
        Schema::table('jobs', function (Blueprint $table) {
            $table->dropConstrainedForeignId('request_log_id');
        });
    }

    public function down()
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
}
