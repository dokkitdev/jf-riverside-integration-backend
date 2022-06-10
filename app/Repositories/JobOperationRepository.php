<?php

namespace App\Repositories;

use App\Models\JobOperation;

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
