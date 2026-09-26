<?php

namespace App\Http\Controllers;

use App\Enums\Departamento;
use App\Enums\MedioRecepcion;
use App\Http\Requests\ReportarRequest;
use App\Services\ReportarAnalisis;
use Illuminate\Http\JsonResponse;

class ReporteController extends Controller
{
    /**
     * Anonymously report a suspicious analysis.
     */
    public function store(ReportarRequest $request, ReportarAnalisis $reportar): JsonResponse
    {
        $reportar->registrar(
            $request->integer('analisis_id'),
            $request->input('comentario'),
            $request->enum('departamento', Departamento::class),
            $request->enum('medio', MedioRecepcion::class),
        );

        return response()->json(['mensaje' => ReportarAnalisis::MENSAJE_EXITO], 201);
    }
}
