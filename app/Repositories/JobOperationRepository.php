<?php

namespace App\Repositories;

use App\Models\JobOperation;
use RonasIT\Support\Repositories\BaseRepository;

/**
 * @property JobOperation $model
*/
class JobOperationRepository extends BaseRepository
{
    public function __construct()
    {
        $this->setModel(JobOperation::class);
    }
}
