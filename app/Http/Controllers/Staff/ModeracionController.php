<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\Reporte;
use App\Services\ModerarReporte;
use Illuminate\Http\JsonResponse;

/**
 * Moderation actions over reports, for any staff member (moderator or admin).
 *
 * The queue is the Livewire page at GET /staff/reportes, so there is no index() here.
 * A missing report is a 404 (route model binding); one that is no longer pending is a 422.
 */
class ModeracionController extends Controller
{
    public function confirmar(Reporte $reporte, ModerarReporte $moderar): JsonResponse
    {
        return $this->respuesta($moderar->confirmar($reporte));
    }

    public function descartar(Reporte $reporte, ModerarReporte $moderar): JsonResponse
    {
        return $this->respuesta($moderar->descartar($reporte));
    }

    private function respuesta(Reporte $reporte): JsonResponse
    {
        return response()->json(['id' => $reporte->id, 'estado' => $reporte->estado->value]);
    }
}
