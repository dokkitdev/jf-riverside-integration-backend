<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class JobsTableCreate extends Migration
{
    public function up()
    {
        Schema::create('jobs', function (Blueprint $table) {
            $table->increments('id');
            $table->string('order_no')->unique();
            $table->string('simpro_job_id')->unique()->nullable();
            $table->timestamps();

            $table->index(['simpro_job_id']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('jobs');
    }
}
