<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

class JobOperationRequestLogTableCreate extends Migration
{
    public function up()
    {
        Schema::create('job_operation_request_log', function (Blueprint $table) {
            $table->id();

            $table
                ->foreignId('job_operation_id')
                ->constrained()
                ->cascadeOnDelete()
                ->restrictOnUpdate();

            $table
                ->foreignId('request_log_id')
                ->constrained()
                ->cascadeOnDelete()
                ->restrictOnUpdate();
        });
    }

    public function down()
    {
        Schema::dropIfExists('job_operation_request_log');
    }
}
