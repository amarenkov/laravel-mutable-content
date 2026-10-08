<?php

namespace Amarenkov\MutableContent\Casts;

use Illuminate\Contracts\Database\Eloquent\Castable;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Contracts\Database\Eloquent\ComparesCastableAttributes;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Casts\Json;

/**
 * Cast of the fields column to a read-only array object.
 */
class AsFields implements Castable
{
    public static function castUsing(array $arguments)
    {
        return new class implements CastsAttributes, ComparesCastableAttributes
        {
            public function get($model, $key, $value, $attributes)
            {
                if (!isset($attributes[$key])) {
                    return null;
                }

                $data = Json::decode($attributes[$key]);

                return is_array($data) ? new ReadOnlyArrayObject($data, ReadOnlyArrayObject::ARRAY_AS_PROPS) : null;
            }

            public function set($model, $key, $value, $attributes)
            {
                return [$key => Json::encode($value)];
            }

            public function compare(Model $model, string $key, mixed $firstValue, mixed $secondValue)
            {
                return Json::decode($firstValue) === Json::decode($secondValue);
            }

            public function serialize($model, string $key, $value, array $attributes)
            {
                return $value->getArrayCopy();
            }
        };
    }
}
