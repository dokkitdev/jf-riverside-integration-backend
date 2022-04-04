<?php

namespace App\Services;

use App\Repositories\JobOperationRepository;
use RonasIT\Support\Services\EntityService;

/**
 * @property JobOperationRepository $repository
 * @mixin JobOperationRepository
 */
class JobOperationService extends EntityService
{
    public function __construct()
    {
        $this->setRepository(JobOperationRepository::class);
    }
}
