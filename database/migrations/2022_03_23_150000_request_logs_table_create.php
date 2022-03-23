<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class RequestLogsTableCreate extends Migration
{
    public function up()
    {
        Schema::create('request_logs', function (Blueprint $table) {
            $table->increments('id');
            $table->timestamps();

            $table->enum('destination', ['self', 'simpro', 'riverside']);
            $table->text('url');
            $table->string('method');
            $table->text('request_body');
            $table->text('request_headers');
            $table->integer('response_status_code');
            $table->text('response_body');
            $table->text('response_headers');
        });
    }

    public function down()
    {
        Schema::dropIfExists('request_logs');
    }
}
