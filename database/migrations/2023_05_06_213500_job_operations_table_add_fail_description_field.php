<?php

use Illuminate\Database\Migrations\Migration;
use App\Support\Traits\MigrationTrait;
use Illuminate\Database\Schema\Blueprint;

class JobOperationsTableAddFailDescriptionField extends Migration
{
    use MigrationTrait;

    public function up()
    {
        Schema::table('job_operations', function (Blueprint $table) {
            $table->text('fail_description')->nullable();
        });
    }

    public function down()
    {
        Schema::table('job_operations', function (Blueprint $table) {
            $table->dropColumn('fail_description');
        });
    }
}
