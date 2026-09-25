<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Http\Requests\UsuarioStaffRequest;
use App\Models\UsuarioStaff;
use Illuminate\Http\JsonResponse;

class UsuarioController extends Controller
{
    /**
     * Create a staff account (admins only, via the admin.staff middleware).
     *
     * The listing is the Livewire page at GET /staff/usuarios, so there is no index() here.
     */
    public function store(UsuarioStaffRequest $request): JsonResponse
    {
        $usuario = UsuarioStaff::create($request->validated());

        return response()->json($usuario->only(['id', 'nombre', 'email', 'rol']), 201);
    }
}
