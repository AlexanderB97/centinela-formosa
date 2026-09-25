<?php

namespace App\Models;

use App\Enums\EstadoReporte;
use Database\Factories\ReporteFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Anonymous report of an analysis: it stores nothing about the visitor.
 *
 * @property int $id
 * @property int $analisis_id
 * @property string|null $comentario
 * @property EstadoReporte $estado
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['analisis_id', 'comentario', 'estado'])]
class Reporte extends Model
{
    /** @use HasFactory<ReporteFactory> */
    use HasFactory;

    protected $table = 'reportes';

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'estado' => 'pendiente',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'estado' => EstadoReporte::class,
        ];
    }

    /**
     * @return BelongsTo<Analisis, $this>
     */
    public function analisis(): BelongsTo
    {
        return $this->belongsTo(Analisis::class);
    }
}
