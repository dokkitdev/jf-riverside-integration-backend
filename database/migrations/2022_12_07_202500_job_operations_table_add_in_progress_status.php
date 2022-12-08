<?php

use Illuminate\Database\Migrations\Migration;
use App\Support\Traits\MigrationTrait;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Schema\Blueprint;

class JobOperationsTableAddInProgressStatus extends Migration
{
    use MigrationTrait;

    public function up()
    {
        $this->changeEnum('job_operations', 'status', ['success', 'fail', 'skip', 'in_progress']);
    }

    public function down()
    {
        DB::table('job_operations')->where('status', 'in_progress')->delete();

        $this->changeEnum('job_operations', 'status', ['success', 'fail', 'skip']);
    }
}
