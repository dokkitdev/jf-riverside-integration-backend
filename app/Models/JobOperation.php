<?php

namespace App\Models;

use RonasIT\Support\Traits\ModelTrait;
use Illuminate\Database\Eloquent\Model;

class JobOperation extends Model
{
    use ModelTrait;

    const OPERATION_RECEIVE_DATA_FROM_RIVERSIDE = 'receive_data_from_riverside';
    const OPERATION_CREATE_JOB_IN_SIMPRO = 'create_job_in_simpro';
    const OPERATION_NOTIFY_RIVERSIDE_ABOUT_CRATED_JOB = 'notify_riverside_about_created_job';

    const OPERATION_RECEIVE_WEBHOOK_JOB_COMPLETE = 'receive_webhook_job_complete';
    const OPERATION_NOTIFY_RIVERSIDE_ABOUT_COMPLETED_JOB = 'notify_riverside_about_completed_job';

    const OPERATION_RECEIVE_WEBHOOK_JOB_SCHEDULE_CREATED = 'receive_webhook_job_schedule_created';
    const OPERATION_NOTIFY_RIVERSIDE_ABOUT_CREATED_SCHEDULE = 'notify_riverside_about_created_schedule';

    const OPERATION_RECEIVE_WEBHOOK_JOB_STATUS_CHANGED = 'receive_webhook_job_status_changed';
    const OPERATION_NOTIFY_RIVERSIDE_ABOUT_CHANGED_JOB_STATUS = 'notify_riverside_about_changed_job_status';

    protected $fillable = [
        'job_id',
        'operation'
    ];

    public function job()
    {
        return $this->belongsTo(Job::class);
    }
}