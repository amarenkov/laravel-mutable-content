<?php

namespace Amarenkov\MutableContent\Helpers;

use Illuminate\Contracts\Auth\Authenticatable;

/**
 * Application users as the packages take them: a user object or an id, a user model from the config.
 */
class UserHelper
{
    // static
    /**
     * @return ($user is null ? null : int)
     */
    public static function id(Authenticatable|int|null $user): ?int
    {
        return $user instanceof Authenticatable ? (int)$user->getAuthIdentifier() : $user;
    }

    /**
     * User model of a package: its user_model setting, the model of the auth config by default.
     *
     * @return class-string<\Illuminate\Database\Eloquent\Model>
     */
    public static function model(string $configKey): string
    {
        return config($configKey.'.user_model') ?? config('auth.providers.users.model');
    }
}
