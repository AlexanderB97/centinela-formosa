<?php

namespace App\Services;

use App\Enums\NivelRiesgo;
use App\Models\Analisis;
use App\Models\CasoConfirmado;
use App\Models\Reporte;

/**
 * Public impact statistics (HU3.1): aggregates only, never the content of an analysis or report.
 * Everything is counted by the database (COUNT / GROUP BY), so no rows are loaded into memory.
 */
class EstadisticasImpacto
{
    /**
     * @return array{total_analisis: int, total_reportes: int, casos_confirmados: int, distribucion_nivel: array{seguro: int, dudoso: int, riesgo: int}}
     */
    public function obtener(): array
    {
        $distribucion = $this->distribucionPorNivel();

        return [
            // Derived from the grouped query: one query less, and the total always equals the sum of the levels.
            'total_analisis' => array_sum($distribucion),
            'total_reportes' => Reporte::count(),
            'casos_confirmados' => CasoConfirmado::count(),
            'distribucion_nivel' => $distribucion,
        ];
    }

    /**
     * One grouped query; levels without analyses come back as 0, always in the contract order.
     *
     * @return array{seguro: int, dudoso: int, riesgo: int}
     */
    private function distribucionPorNivel(): array
    {
        $totales = Analisis::query()
            ->toBase()
            ->select('nivel')
            ->selectRaw('count(*) as total')
            ->groupBy('nivel')
            ->pluck('total', 'nivel');

        $distribucion = [];

        foreach (NivelRiesgo::cases() as $nivel) {
            $distribucion[$nivel->value] = (int) ($totales[$nivel->value] ?? 0);
        }

        return $distribucion;
    }
}
