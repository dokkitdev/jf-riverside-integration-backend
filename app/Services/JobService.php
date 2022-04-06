<?php

namespace App\Services;

use App\Repositories\JobRepository;
use RonasIT\Support\Services\EntityService;

/**
 * @property JobRepository $repository
 * @mixin JobRepository
 */
class JobService extends EntityService
{
    public function __construct()
    {
        $this->setRepository(JobRepository::class);
    }
}
