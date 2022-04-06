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

    protected $fillable = [
        'job_id',
        'operation'
    ];

    public function job()
    {
        return $this->belongsTo(Job::class);
    }
}