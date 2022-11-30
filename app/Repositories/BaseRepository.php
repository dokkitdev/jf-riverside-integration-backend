<?php

namespace App\Repositories;

use App\Resources\Resource;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use RonasIT\Support\Repositories\BaseRepository as RonasItBaseRepository;

abstract class BaseRepository extends RonasItBaseRepository
{
    /**
     * @param array|Resource $data
     */
    public function create($data): Model
    {
        if ($data instanceof Resource) {
            $data = $data->toArray();
        }

        return parent::create($data);
    }

    public function upsert(array $where, array $setOnInsertData, array $updateData): Model
    {
        $entityData = Arr::only(array_merge($setOnInsertData, $updateData), $this->fields);

        $modelClass = get_class($this->model);
        $model = new $modelClass();

        if ($this->forceMode) {
            $model->forceFill($entityData);
        } else {
            $model->fill(Arr::only($entityData, $model->getFillable()));
        }

        $model::upsert(
            array_merge($where, $model->getAttributes()),
            array_keys($where),
            array_keys($updateData),
        );

        return $this->first($where);
    }
}
