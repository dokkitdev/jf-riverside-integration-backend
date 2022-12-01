<?php

use Illuminate\Database\Migrations\Migration;
use App\Support\Traits\MigrationTrait;
use Illuminate\Support\Facades\DB;

class JobOperationsTableChangeOperationFieldTypeToString extends Migration
{
    use MigrationTrait;

    public function up()
    {
        $this->dropEnumCheck('job_operations', 'operation');
    }

    public function down()
    {
        $values = [
            'receive_data_from_riverside',
            'create_job_in_simpro',
            'notify_riverside_about_created_job',

            'receive_webhook_job_complete',
            'notify_riverside_about_completed_job',

            'receive_webhook_job_schedule_created',
            'notify_riverside_about_created_schedule',

            'receive_webhook_job_status_changed',
            'notify_riverside_about_changed_job_status',

            'update_site_info',
        ];

        DB::table('job_operations')
            ->whereNotIn('operation', $values)
            ->delete();

        $this->addEnumCheck('job_operations', 'operation', $values);
    }
}
