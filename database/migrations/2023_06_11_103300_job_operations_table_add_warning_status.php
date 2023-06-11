<?php

use Illuminate\Database\Migrations\Migration;
use App\Support\Traits\MigrationTrait;

class JobOperationsTableAddWarningStatus extends Migration
{
    use MigrationTrait;

    public function up()
    {
        $this->changeEnum('job_operations', 'status', ['success', 'fail', 'skip', 'in_progress', 'warning']);
    }

    public function down()
    {
        $this->changeEnum('job_operations', 'status', ['success', 'fail', 'skip', 'in_progress'], ['warning' => 'fail']);
    }
}
