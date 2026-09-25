<?php

namespace App\Http\Controllers;

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
        $reportar->registrar($request->integer('analisis_id'), $request->input('comentario'));

        return response()->json(['mensaje' => ReportarAnalisis::MENSAJE_EXITO], 201);
    }
}
