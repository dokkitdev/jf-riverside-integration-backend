<?php

use Illuminate\Database\Migrations\Migration;

class JobOperationsTableAddNewOperationsToEnum extends Migration
{
    public function up()
    {
        $this->changeEnum([
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

    public function down()
    {
        $this->changeEnum([
            'receive_data_from_riverside',
            'create_job_in_simpro',
            'notify_riverside_about_created_job',

            'receive_webhook_job_complete',
            'notify_riverside_about_completed_job',
        ]);
    }

    protected function changeEnum($types)
    {
        DB::statement("ALTER TABLE job_operations DROP CONSTRAINT job_operations_operation_check");

        $result = join(', ', array_map(function($value) {
            return sprintf("'%s'::character varying", $value);
        }, $types));

        DB::statement("ALTER TABLE job_operations add CONSTRAINT job_operations_operation_check CHECK ((operation)::text = ANY ((ARRAY[$result])::text[]))");
    }
}
