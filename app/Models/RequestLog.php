<?php

namespace App\Models;

use RonasIT\Support\Traits\ModelTrait;
use Illuminate\Database\Eloquent\Model;

class RequestLog extends Model
{
    use ModelTrait;

    const DESTINATION_SELF = 'self';
    const DESTINATION_SIMPRO = 'simpro';
    const DESTINATION_RIVERSIDE = 'riverside';

    protected $fillable = [
        'destination',
        'url',
        'method',
        'request_body',
        'request_headers',
        'response_status_code',
        'response_body',
        'response_headers',
    ];
}
