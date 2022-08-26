<?php

use Illuminate\Database\Migrations\Migration;
use App\Support\Traits\MigrationTrait;

class JobOperationsTableAddUpdateSiteInfoOperation extends Migration
{
    use MigrationTrait;

    public function up()
    {
        $this->changeEnum('job_operations', 'operation', [
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
        ]);
    }

    public function down()
    {
        $this->changeEnum('job_operations', 'operation', [
            'receive_data_from_riverside',
            'create_job_in_simpro',
            'notify_riverside_about_created_job',

            'receive_webhook_job_complete',
            'notify_riverside_about_completed_job',

            'receive_webhook_job_schedule_created',
            'notify_riverside_about_created_schedule',

            'receive_webhook_job_status_changed',
            'notify_riverside_about_changed_job_status',
        ]);
    }
}
