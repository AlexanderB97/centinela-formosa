<?php

namespace App\Http\Controllers;

use App\Http\Requests\AnalizarRequest;
use App\Services\RiskAnalyzer;
use Illuminate\Http\JsonResponse;

class AnalisisController extends Controller
{
    /**
     * Analyze a text, link or decoded QR and return its risk level.
     */
    public function store(AnalizarRequest $request, RiskAnalyzer $analyzer): JsonResponse
    {
        $resultado = $analyzer->analizar($request->string('tipo')->value(), $request->string('contenido')->value());

        return response()->json($resultado);
    }
}
