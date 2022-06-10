<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;

class RequestLogsTableMakeResponseFieldsNullable extends Migration
{
    public function up()
    {
        Schema::table('request_logs', function (Blueprint $table) {
            $table->integer('response_status_code')->nullable(true)->change();
            $table->text('response_body')->nullable(true)->change();
            $table->text('response_headers')->nullable(true)->change();
        });
    }

    public function down()
    {
        DB::table('request_logs')
            ->whereNull('response_status_code')
            ->update([
                'response_status_code' => 0,
            ]);

        DB::table('request_logs')
            ->whereNull('response_body')
            ->update([
                'response_body' => '',
            ]);

        DB::table('request_logs')
            ->whereNull('response_headers')
            ->update([
                'response_headers' => '',
            ]);

        Schema::table('request_logs', function (Blueprint $table) {
            $table->integer('response_status_code')->nullable(false)->change();
            $table->text('response_body')->nullable(false)->change();
            $table->text('response_headers')->nullable(false)->change();
        });
    }
}
