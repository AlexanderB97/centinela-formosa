<?php

namespace Database\Factories;

use App\Enums\TipoContenido;
use App\Models\CasoConfirmado;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CasoConfirmado>
 */
class CasoConfirmadoFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tipo' => TipoContenido::Texto,
            'contenido' => fake()->unique()->sentence(),
        ];
    }

    /**
     * A confirmed phishing link.
     */
    public function link(?string $url = null): static
    {
        return $this->state(fn () => [
            'tipo' => TipoContenido::Link,
            'contenido' => $url ?? 'https://'.fake()->unique()->domainWord().'-verificacion.xyz/login',
        ]);
    }
}
