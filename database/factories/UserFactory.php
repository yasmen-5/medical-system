<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    public function definition(): array
    {
        return [
            'email' => fake()->unique()->safeEmail(),
            'phone_number' => fake()->optional()->numerify('##########'),
            'password_hash' => Hash::make('password'),
            'role' => 'PATIENT',
            'account_status' => 'VERIFIED',
            'email_verified' => true,
            'mfa_method' => 'NONE',
            'mfa_secret' => null,
        ];
    }
}
