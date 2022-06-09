<?php

namespace App\Repositories;

use App\Models\Role;

/**
 * @property  Role $model
*/
class RoleRepository extends BaseRepository
{
    public function __construct()
    {
        $this->setModel(Role::class);
    }
}
