<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class QueueWorkerJobsTableAddStreamField extends Migration
{
    public function up()
    {
        Schema::table('queue_worker_jobs', function (Blueprint $table) {
            $table->string('stream')->nullable();

            $table->index(['queue', 'stream']);
        });
    }

    public function down()
    {
        Schema::table('queue_worker_jobs', function (Blueprint $table) {
            $table->dropIndex(['queue', 'stream']);

            $table->dropColumn('stream');
        });
    }
}