<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\User>
 */
class UserFactory extends Factory
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
        $prenom = fake()->firstName();
        $nom = fake()->lastName();

        $login = Str::slug($prenom . '.' . $nom, '.') . fake()->unique()->numberBetween(10, 999);
        $pin = str_pad((string) random_int(0, 9999), 4, '0', STR_PAD_LEFT);

        return [
            'name' => $nom,
            'prenom' => $prenom,
            'login' => $login,
            'contact' => fake()->unique()->numerify('07########'),
            'matricule' => strtoupper(fake()->bothify('USR-####')),
            'avatar' => null,
            'role' => fake()->randomElement(['admin', 'agent', 'driver']),
            'code_pin' => Hash::make($pin),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
        ];
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => []);
    }
}
