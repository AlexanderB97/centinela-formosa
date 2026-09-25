<?php

namespace Database\Factories;

use App\Enums\RolStaff;
use App\Models\UsuarioStaff;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<UsuarioStaff>
 */
class UsuarioStaffFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'nombre' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'password' => static::$password ??= Hash::make('password'),
            'rol' => RolStaff::Moderador,
            'remember_token' => Str::random(10),
        ];
    }

    /**
     * Indicate that the staff member is an admin.
     */
    public function admin(): static
    {
        return $this->state(fn (array $attributes) => [
            'rol' => RolStaff::Admin,
        ]);
    }
}
