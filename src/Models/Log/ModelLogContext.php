<?php

namespace Amarenkov\MutableContent\Models\Log;

use Illuminate\Database\Eloquent\Model;

use Amarenkov\MutableContent\Domain\Log\LogData;

/**
 * Context of the log entry written by the next save() or delete() of the model, kept in the model until a successful write.
 */
class ModelLogContext extends LogData
{
    // public
    public function __construct(
        protected Model $model
    ) {
    }

    public function save(array $options = []): bool
    {
        return $this->model->save($options);
    }

    public function delete(): ?bool
    {
        return $this->model->delete();
    }
}
