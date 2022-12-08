<?php

use Illuminate\Database\Migrations\Migration;
use App\Support\Traits\MigrationTrait;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Schema\Blueprint;

class JobOperationsTableMakeJobIdNullable extends Migration
{
    use MigrationTrait;

    public function up()
    {
        Schema::table('job_operations', function (Blueprint $table) {
            $table->foreignId('job_id')->nullable(true)->change();
        });
    }

    public function down()
    {
        DB::table('job_operations')->whereNull('job_id')->delete();

        Schema::table('job_operations', function (Blueprint $table) {
            $table->foreignId('job_id')->nullable(false)->change();
        });
    }
}
