<?php

namespace App\Models;

use App\Enums\TipoContenido;
use App\Services\Analisis\Huella;
use Database\Factories\CasoConfirmadoFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Content the staff already confirmed as phishing.
 *
 * @property int $id
 * @property TipoContenido $tipo
 * @property string $contenido
 * @property string $huella
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['tipo', 'contenido'])]
class CasoConfirmado extends Model
{
    /** @use HasFactory<CasoConfirmadoFactory> */
    use HasFactory;

    protected $table = 'casos_confirmados';

    protected static function booted(): void
    {
        static::saving(function (CasoConfirmado $caso) {
            $caso->huella = Huella::de($caso->contenido);
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
        ];
    }
}
