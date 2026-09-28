<?php

namespace Database\Factories;

use App\Enums\EstadoNoticia;
use App\Models\Noticia;
use App\Models\UsuarioStaff;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Noticia>
 */
class NoticiaFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'titulo' => fake()->sentence(6),
            // asText: true returns the paragraphs already joined, so the type is always a string.
            'cuerpo' => fake()->paragraphs(3, asText: true),
            'imagen_ruta' => null,
            'estado' => EstadoNoticia::Borrador,
            'publicada_en' => null,
            'usuario_staff_id' => UsuarioStaff::factory(),
        ];
    }

    public function publicada(?string $fecha = null): static
    {
        return $this->state(fn () => [
            'estado' => EstadoNoticia::Publicada,
            'publicada_en' => $fecha ?? now(),
        ]);
    }
}
