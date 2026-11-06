<?php

namespace Amarenkov\MutableContent\Models\Concerns;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Builder;

use Amarenkov\MutableContent\Helpers\UserHelper;

/**
 * Records of a user kept in a field: FIELD_USER_ID of the class by default.
 */
trait OwnedByUser
{
    // public
    public function ownerField(): string
    {
        return static::FIELD_USER_ID;
    }

    public function scopeOwnedBy(Builder $query, Authenticatable|int $user): Builder
    {
        return $query->whereField($this->ownerField(), UserHelper::id($user));
    }
}
