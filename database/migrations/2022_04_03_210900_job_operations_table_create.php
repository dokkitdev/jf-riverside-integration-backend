<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class JobOperationsTableCreate extends Migration
{
    public function up()
    {
        Schema::create('job_operations', function (Blueprint $table) {
            $table->increments('id');
            $table->enum('operation', [
                'receive_data_from_riverside',
                'create_job_in_simpro',
                'notify_riverside_about_created_job',

                'receive_webhook_job_complete',
                'notify_riverside_about_completed_job',
            ]);
            $table->timestamps();

            $table
                ->foreignId('job_id')
                ->references('id')
                ->on('jobs')
                ->onDelete('cascade');

            $table->index(['job_id', 'operation']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('job_operations');
    }
}
