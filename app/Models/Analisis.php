<?php

namespace App\Models;

use App\Enums\NivelRiesgo;
use App\Enums\TipoContenido;
use App\Services\Analisis\Huella;
use Database\Factories\AnalisisFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property TipoContenido $tipo
 * @property string $contenido
 * @property string|null $huella
 * @property NivelRiesgo $nivel
 * @property list<string> $razones
 * @property string $explicacion
 * @property bool $explicacion_generada_por_ia
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['tipo', 'contenido', 'nivel', 'razones', 'explicacion', 'explicacion_generada_por_ia'])]
class Analisis extends Model
{
    /** @use HasFactory<AnalisisFactory> */
    use HasFactory;

    protected $table = 'analisis';

    protected static function booted(): void
    {
        // Same fingerprint as CasoConfirmado: groups repeated content regardless of casing or spacing.
        static::saving(function (Analisis $analisis) {
            if ($analisis->isDirty('contenido')) {
                $analisis->huella = Huella::de($analisis->contenido);
            }
        });
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tipo' => TipoContenido::class,
            'nivel' => NivelRiesgo::class,
            'razones' => 'array',
            'explicacion_generada_por_ia' => 'boolean',
        ];
    }
}
