<?php

namespace App\Models;

use RonasIT\Support\Traits\ModelTrait;
use Illuminate\Database\Eloquent\Model;

class Job extends Model
{
    use ModelTrait;

    protected $fillable = [
        'order_no',
        'simpro_job_id',
        'is_appointment_sent',
        'request_log_id',
    ];

    public function operations()
    {
        return $this->hasMany(JobOperation::class);
    }
}