<?php

namespace App\Services;

use App\Enums\EstadoReporte;
use App\Models\Analisis;
use App\Models\CasoConfirmado;
use App\Models\Reporte;
use App\Services\Analisis\Huella;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Staff moderation of reports. Used by the POST /staff/reportes/{reporte}/* endpoints and by
 * the Livewire moderation queue, so the "confirm → confirmed case" rule lives in one place.
 */
class ModerarReporte
{
    /**
     * Mark the report as confirmed and create (or refresh) the confirmed case for its content.
     *
     * @throws ValidationException when the report is no longer pending.
     */
    public function confirmar(Reporte $reporte): Reporte
    {
        return DB::transaction(function () use ($reporte) {
            $this->cambiarEstado($reporte, EstadoReporte::Confirmado);
            $this->registrarCaso($reporte->analisis);

            return $reporte->refresh();
        });
    }

    /**
     * Mark the report as discarded. Confirmed cases are not touched.
     *
     * @throws ValidationException when the report is no longer pending.
     */
    public function descartar(Reporte $reporte): Reporte
    {
        $this->cambiarEstado($reporte, EstadoReporte::Descartado);

        return $reporte->refresh();
    }

    /**
     * Atomic transition out of "pendiente": the WHERE on the current state means only one of two
     * concurrent moderators (or a stale model loaded before someone else acted) can win the UPDATE.
     */
    private function cambiarEstado(Reporte $reporte, EstadoReporte $estado): void
    {
        $actualizados = Reporte::query()
            ->whereKey($reporte->getKey())
            ->where('estado', EstadoReporte::Pendiente->value)
            ->update(['estado' => $estado->value]);

        if ($actualizados === 0) {
            throw ValidationException::withMessages([
                'reporte' => "El reporte #{$reporte->getKey()} ya no está pendiente: otro moderador ya lo resolvió.",
            ]);
        }
    }

    private function registrarCaso(Analisis $analisis): CasoConfirmado
    {
        $huella = Huella::de($analisis->contenido);

        try {
            // Savepoint: if the insert hits the unique index, only this part is rolled back.
            return DB::transaction(fn () => $this->guardarCaso($huella, $analisis));
        } catch (UniqueConstraintViolationException) {
            // Another report with the same content was confirmed at the same time: refresh that case.
            return $this->guardarCaso($huella, $analisis);
        }
    }

    /**
     * One case per fingerprint: an existing case is re-saved with the (equivalent) content instead of duplicated.
     */
    private function guardarCaso(string $huella, Analisis $analisis): CasoConfirmado
    {
        $caso = CasoConfirmado::query()->where('huella', $huella)->first() ?? new CasoConfirmado;

        $caso->fill(['tipo' => $analisis->tipo, 'contenido' => $analisis->contenido]);

        // touch() also saves any change and records the re-confirmation in updated_at.
        $caso->exists ? $caso->touch() : $caso->save();

        return $caso;
    }
}
