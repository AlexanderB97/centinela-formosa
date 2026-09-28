<?php

namespace App\Models;

use App\Enums\Barrio;
use App\Enums\Departamento;
use App\Enums\EstadoReporte;
use App\Enums\MedioRecepcion;
use Database\Factories\ReporteFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Anonymous report of an analysis: it stores nothing about the visitor.
 *
 * departamento and medio are optional context of the message (where and how it arrived), not
 * data about who reported it. Even so, in small departments their combination with the date and
 * the comment narrows things down: staff sees them per report, the public only aggregated.
 * The public map (/mapa) shows barrio only per neighborhood and with at least
 * MapaZonasAfectadas::MINIMO_REPORTES reports; any new public use must keep that rule.
 * barrio is only set when departamento is formosa_capital.
 *
 * @property int $id
 * @property int $analisis_id
 * @property string|null $comentario
 * @property Departamento|null $departamento
 * @property Barrio|null $barrio
 * @property MedioRecepcion|null $medio
 * @property EstadoReporte $estado
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['analisis_id', 'comentario', 'departamento', 'barrio', 'medio', 'estado'])]
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
            'departamento' => Departamento::class,
            'barrio' => Barrio::class,
            'medio' => MedioRecepcion::class,
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
