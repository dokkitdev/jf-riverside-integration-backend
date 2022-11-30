<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;

class JobOperationsTableAddDetailsAndStatusFields extends Migration
{
    public function up()
    {
        Schema::table('job_operations', function (Blueprint $table) {
            $table->json('details')->nullable();
            $table->enum('status', ['success', 'fail', 'skip'])->nullable(true);
        });

        DB::table('job_operations')->update(['status' => 'success']);

        Schema::table('job_operations', function (Blueprint $table) {
            $table->string('status')->nullable(false)->change();
        });
    }

    public function down()
    {
        Schema::table('job_operations', function (Blueprint $table) {
            $table->dropColumn('details');
            $table->dropColumn('status');
        });
    }
}
