<?php

namespace App\Kernel\Base;

use App\Exceptions\Common\AppException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

abstract class BaseDao
{
    protected array $selectColumns = ['*'];

    abstract protected function getModel(): Model;

    protected function newBuilder(): Builder
    {
        return $this->getModel()->newQuery();
    }

    public function find($id, array $columns = [])
    {
        return $this->newBuilder()->select($columns ?: $this->selectColumns)->find($id);
    }

    public function findOrFail($id, array $columns = [])
    {
        $model = $this->find($id, $columns);

        if (!$model) {
            throw new AppException(110006);
        }

        return $model;
    }

    public function store(array $params, array $extra = []): Model
    {
        $model = $this->getModel();
        $model->fill($params);
        $model->forceFill($extra);
        $model->save();

        return $model;
    }

    public function findByParams(array $params, array $columns = [])
    {
        $query = $this->newBuilder()->select($columns ?: $this->selectColumns);

        foreach ($params as $field => $value) {
            if (filled($value)) {
                $query->where($field, $value);
            }
        }

        return $query->first();
    }
}
