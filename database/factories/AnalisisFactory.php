<?php

namespace Database\Factories;

use App\Enums\NivelRiesgo;
use App\Enums\TipoContenido;
use App\Models\Analisis;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Analisis>
 */
class AnalisisFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $nivel = fake()->randomElement(NivelRiesgo::cases());

        return [
            'tipo' => TipoContenido::Texto,
            'contenido' => fake()->sentence(),
            'nivel' => $nivel,
            'razones' => ['No se detectaron patrones típicos de estafa.'],
            'explicacion' => $nivel->explicacionDeRespaldo(),
            'explicacion_generada_por_ia' => false,
        ];
    }
}
