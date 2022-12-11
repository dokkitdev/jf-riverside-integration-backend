<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use RonasIT\Support\Traits\ModelTrait;
use Illuminate\Database\Eloquent\Model;

class JobOperation extends Model
{
    use ModelTrait;

    const OPERATION_CREATE = 'create';
    const OPERATION_UPDATE_SITE_INFO = 'update_site_info';
    const OPERATION_REGISTER_WEBHOOK = 'register_webhook';
    const OPERATION_HANDLE_WEBHOOK = 'handle_webhook';

    public const STATUS_SUCCESS = 'success';
    public const STATUS_FAIL = 'fail';
    public const STATUS_SKIP = 'skip';
    public const STATUS_IN_PROGRESS = 'in_progress';

    protected bool $shouldBePersisted = true;

    protected $fillable = [
        'job_id',
        'operation',
        'details',
        'status',
    ];

    protected $casts = [
        'details' => 'array',
    ];

    public function job()
    {
        return $this->belongsTo(Job::class);
    }

    public function requestLogs(): BelongsToMany
    {
        return $this->belongsToMany(RequestLog::class);
    }

    public function interruptPersisting()
    {
        $this->shouldBePersisted = false;
    }

    public function shouldBePersisted(): bool
    {
        return $this->shouldBePersisted;
    }
}
