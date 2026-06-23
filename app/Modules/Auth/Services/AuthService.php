<?php

declare(strict_types=1);

namespace App\Modules\Auth\Services;

use App\Models\User;

class AuthService
{
    /**
     * Register a new user. The password is hashed automatically by the
     * model's `hashed` cast.
     *
     * @param  array{name: string, email: string, password: string}  $data
     */
    public function register(array $data): User
    {
        return User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => $data['password'],
        ]);
    }
}
