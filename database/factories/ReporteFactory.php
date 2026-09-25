<?php

namespace Database\Factories;

use App\Enums\EstadoReporte;
use App\Models\Analisis;
use App\Models\Reporte;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Reporte>
 */
class ReporteFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'analisis_id' => Analisis::factory(),
            'comentario' => fake()->optional()->sentence(),
            'estado' => EstadoReporte::Pendiente,
        ];
    }
}
