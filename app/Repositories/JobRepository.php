<?php

namespace App\Repositories;

use App\Models\Job;
use RonasIT\Support\Repositories\BaseRepository;

/**
 * @property Job $model
*/
class JobRepository extends BaseRepository
{
    public function __construct()
    {
        $this->setModel(Job::class);
    }
}
