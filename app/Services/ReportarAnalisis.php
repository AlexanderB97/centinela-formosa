<?php

namespace App\Services;

use App\Enums\Departamento;
use App\Enums\MedioRecepcion;
use App\Http\Requests\ReportarRequest;
use App\Models\Reporte;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Validation\ValidationException;

/**
 * Registers an anonymous report of an analysis. Used by POST /reportar and by the
 * Livewire analyzer, both after validating with ReportarRequest::reglas().
 */
class ReportarAnalisis
{
    public const MENSAJE_EXITO = '¡Gracias! Tu reporte fue enviado de forma anónima.';

    /**
     * departamento and medio are optional context of the message, never data about the visitor.
     *
     * @throws ValidationException when the analysis was already reported (e.g. two concurrent requests).
     */
    public function registrar(int $analisisId, ?string $comentario, ?Departamento $departamento = null, ?MedioRecepcion $medio = null): Reporte
    {
        $comentario = trim((string) $comentario);

        try {
            return Reporte::create([
                'analisis_id' => $analisisId,
                'comentario' => $comentario === '' ? null : $comentario,
                'departamento' => $departamento,
                'medio' => $medio,
            ]);
        } catch (UniqueConstraintViolationException) {
            // Validation already checks this; the unique index covers the race between two requests.
            throw ValidationException::withMessages(['analisis_id' => ReportarRequest::MENSAJE_YA_REPORTADO]);
        }
    }
}
