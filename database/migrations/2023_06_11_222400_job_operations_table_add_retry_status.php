<?php

use Illuminate\Database\Migrations\Migration;
use App\Support\Traits\MigrationTrait;

class JobOperationsTableAddRetryStatus extends Migration
{
    use MigrationTrait;

    public function up()
    {
        $this->changeEnum('job_operations', 'status', ['success', 'fail', 'skip', 'in_progress', 'warning', 'retry']);
    }

    public function down()
    {
        $this->changeEnum('job_operations', 'status', ['success', 'fail', 'skip', 'in_progress', 'warning'], ['retry' => 'in_progress']);
    }
}
