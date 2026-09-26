<?php

namespace App\Services;

use App\Enums\NivelRiesgo;
use App\Enums\TipoContenido;
use App\Models\Analisis;
use App\Models\CasoConfirmado;
use App\Services\Analisis\Enmascarador;
use Illuminate\Support\Collection;

/**
 * Public ranking of the most analyzed suspicious content, grouped by normalized content (huella)
 * and split by type. Counting is done by the database; only the winners' content is read.
 *
 * Privacy safeguards: content must repeat at least MINIMO_REPETICIONES times, content that was
 * only ever "seguro" is excluded, and what is shown is masked and truncated (Enmascarador).
 */
class RankingConsultados
{
    public const LIMITE = 5;

    public const MINIMO_REPETICIONES = 3;

    /**
     * @return array{texto: list<array{contenido: string, veces: int, nivel: string, confirmado: bool}>, link: list<array{contenido: string, veces: int, nivel: string, confirmado: bool}>, qr: list<array{contenido: string, veces: int, nivel: string, confirmado: bool}>}
     */
    public function obtener(): array
    {
        $grupos = collect(TipoContenido::cases())
            ->mapWithKeys(fn (TipoContenido $tipo) => [$tipo->value => $this->topPorTipo($tipo)]);

        $todos = $grupos->flatten(1);

        $contenidos = $todos->isEmpty() ? collect() : Analisis::query()
            ->toBase()
            ->whereIn('id', $todos->pluck('ultimo_id'))
            ->pluck('contenido', 'id');

        $confirmadas = $todos->isEmpty() ? collect() : CasoConfirmado::query()
            ->toBase()
            ->whereIn('huella', $todos->pluck('huella'))
            ->pluck('huella')
            ->flip();

        return $grupos->map(fn (Collection $grupo) => $grupo->map(fn (object $fila) => [
            'contenido' => Enmascarador::contenido((string) $contenidos[$fila->ultimo_id]),
            'veces' => (int) $fila->veces,
            // Highest level ever detected for this content: errs on the side of caution.
            'nivel' => (int) $fila->en_riesgo > 0 ? NivelRiesgo::Riesgo->value : NivelRiesgo::Dudoso->value,
            'confirmado' => $confirmadas->has($fila->huella),
        ])->values()->all())->all();
    }

    /**
     * One grouped query per type. "seguro"-only content is filtered in SQL, before the LIMIT.
     *
     * @return Collection<int, object{huella: string, veces: int, ultimo_id: int, en_riesgo: int}>
     */
    private function topPorTipo(TipoContenido $tipo): Collection
    {
        return Analisis::query()
            ->toBase()
            ->select('huella')
            ->selectRaw('count(*) as veces, max(id) as ultimo_id')
            ->selectRaw('sum(case when nivel = ? then 1 else 0 end) as en_riesgo', [NivelRiesgo::Riesgo->value])
            ->where('tipo', $tipo->value)
            ->whereNotNull('huella')
            ->groupBy('huella')
            ->havingRaw('count(*) >= ?', [self::MINIMO_REPETICIONES])
            ->havingRaw('sum(case when nivel <> ? then 1 else 0 end) > 0', [NivelRiesgo::Seguro->value])
            ->orderByDesc('veces')
            ->orderByDesc('ultimo_id')
            ->limit(self::LIMITE)
            ->get();
    }
}
