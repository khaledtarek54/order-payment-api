<?php

declare(strict_types=1);

namespace App\Modules\Auth\Actions;

use App\Models\User;

/**
 * Registers a new user. The password is hashed automatically by the model's
 * `hashed` cast.
 */
final class RegisterUserAction
{
    /**
     * @param  array{name: string, email: string, password: string}  $data
     */
    public function execute(array $data): User
    {
        return User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => $data['password'],
        ]);
    }
}
