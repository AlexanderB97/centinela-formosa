<?php

use App\Enums\RolStaff;
use App\Http\Requests\UsuarioStaffRequest;
use App\Models\UsuarioStaff;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts::staff')] #[Title('Gestión de staff')] class extends Component {
    // DEPRECATED: ya no se usa, el listado real sale de cuentas(). Se deja como referencia.
    /**
     * MOCK: datos de ejemplo en memoria solo para maquetar. Backend los reemplaza
     * por el listado real de cuentas de staff. Nunca incluir password ni hash.
     *
     * @var array<int, array{name: string, email: string, rol: string}>
     */
    public array $usuarios = [
        ['name' => 'Laura Giménez', 'email' => 'laura.gimenez@centinela.test', 'rol' => 'admin'],
        ['name' => 'Martín Acosta', 'email' => 'martin.acosta@centinela.test', 'rol' => 'moderador'],
        ['name' => 'Sofía Benítez', 'email' => 'sofia.benitez@centinela.test', 'rol' => 'moderador'],
    ];

    public string $nombre = '';
    public string $email = '';
    public string $password = '';
    public string $rol = '';

    public string $estado = '';

    /**
     * Cuentas reales de staff. Solo se leen id, nombre, email y rol: el password nunca sale de la base.
     *
     * @return Collection<int, UsuarioStaff>
     */
    #[Computed]
    public function cuentas(): Collection
    {
        return UsuarioStaff::query()->orderBy('nombre')->get(['id', 'nombre', 'email', 'rol']);
    }

    /**
     * Alta real de una cuenta de staff, con las mismas reglas que POST /staff/usuarios.
     */
    public function crear(): void
    {
        // Defensa extra además del middleware admin.staff (que también corre en cada request de Livewire).
        abort_unless(auth('staff')->user()?->isAdmin(), 403);

        $this->estado = '';
        $this->email = UsuarioStaffRequest::normalizarEmail($this->email);

        $datos = $this->validate(UsuarioStaffRequest::reglas(), UsuarioStaffRequest::mensajes());

        // El modelo castea el password a hashed: la cuenta puede loguearse en /staff/login enseguida.
        UsuarioStaff::create($datos);

        $this->estado = "Cuenta de {$this->nombre} creada.";

        $this->reset('nombre', 'email', 'password', 'rol');
        unset($this->cuentas);
    }

    // DEPRECATED: ya no se usa, crear() con UsuarioStaff real lo reemplaza. Se deja como referencia.
    /**
     * MOCK: simula el alta en memoria. Backend reemplaza esto con el POST real
     * (validación definitiva, hash del password, persistencia y autorización).
     */
    private function crearMock(): void
    {
        $this->estado = '';
        $this->email = Str::lower(trim($this->email));

        $this->validate([
            'nombre' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::notIn(array_column($this->usuarios, 'email'))],
            'password' => ['required', 'string', 'min:8'],
            'rol' => ['required', Rule::in(['moderador', 'admin'])],
        ], [
            'nombre.required' => 'El nombre es obligatorio.',
            'email.required' => 'El email es obligatorio.',
            'email.email' => 'El email no tiene un formato válido.',
            'email.not_in' => 'Ya existe una cuenta con ese email.',
            'password.required' => 'La contraseña es obligatoria.',
            'password.min' => 'La contraseña debe tener al menos 8 caracteres.',
            'rol.required' => 'Seleccioná un rol.',
            'rol.in' => 'El rol seleccionado no es válido.',
        ]);

        // El password no se guarda en el listado.
        $this->usuarios[] = ['name' => $this->nombre, 'email' => $this->email, 'rol' => $this->rol];

        $this->estado = "Cuenta de {$this->nombre} creada.";

        $this->reset('nombre', 'email', 'password', 'rol');
    }
}; ?>

<div class="flex flex-col gap-6">
    <h1 class="text-2xl font-semibold text-neutral-900">{{ __('Gestión de staff') }}</h1>

    <div role="status" aria-live="polite">
        @if ($estado)
            <div class="rounded-md border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800">
                {{ $estado }}
            </div>
        @endif
    </div>

    <div class="grid gap-6 lg:grid-cols-3">
        {{-- Listado --}}
        <section class="rounded-xl bg-white shadow-sm lg:col-span-2">
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <caption class="px-6 pt-6 pb-4 text-left text-lg font-medium text-neutral-900">
                        {{ __('Cuentas de staff') }}
                    </caption>
                    <thead class="border-b border-neutral-200 text-neutral-600">
                        <tr>
                            <th scope="col" class="px-6 py-3 font-medium">{{ __('Nombre') }}</th>
                            <th scope="col" class="px-6 py-3 font-medium">{{ __('Email') }}</th>
                            <th scope="col" class="px-6 py-3 font-medium">{{ __('Rol') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-neutral-100 text-neutral-900">
                        @foreach ($this->cuentas as $cuenta)
                            <tr wire:key="usuario-{{ $cuenta->id }}">
                                <td class="whitespace-nowrap px-6 py-3">{{ $cuenta->nombre }}</td>
                                <td class="whitespace-nowrap px-6 py-3">{{ $cuenta->email }}</td>
                                <td class="px-6 py-3">
                                    <span @class([
                                        'inline-block rounded-full px-2.5 py-0.5 text-xs font-medium',
                                        'bg-[#8a5a1f] text-white' => $cuenta->rol === RolStaff::Admin,
                                        'bg-neutral-200 text-neutral-800' => $cuenta->rol !== RolStaff::Admin,
                                    ])>
                                        {{ ucfirst($cuenta->rol->value) }}
                                    </span>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>

        {{-- Alta --}}
        <section class="rounded-xl bg-white p-6 shadow-sm" aria-labelledby="alta-titulo">
            <h2 id="alta-titulo" class="mb-4 text-lg font-medium text-neutral-900">{{ __('Nueva cuenta') }}</h2>

            @if ($errors->any())
                <div id="alta-errores" role="alert" class="mb-4 rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                    <ul class="list-inside list-disc">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            @php
                $claseInput = 'w-full rounded-md border border-neutral-300 bg-white px-3 py-2 text-neutral-900 placeholder:text-neutral-400 focus:border-[#8a5a1f] focus:outline-none focus:ring-2 focus:ring-[#8a5a1f]/40';
            @endphp

            <form wire:submit="crear" class="flex flex-col gap-4">
                <div class="flex flex-col gap-2">
                    <label for="nombre" class="text-sm font-medium text-neutral-800">{{ __('Nombre') }}</label>
                    <input
                        id="nombre"
                        type="text"
                        wire:model="nombre"
                        required
                        autocomplete="off"
                        @error('nombre') aria-invalid="true" aria-describedby="alta-errores" @enderror
                        class="{{ $claseInput }}"
                    />
                </div>

                <div class="flex flex-col gap-2">
                    <label for="email" class="text-sm font-medium text-neutral-800">{{ __('Email') }}</label>
                    <input
                        id="email"
                        type="email"
                        wire:model="email"
                        required
                        autocomplete="off"
                        placeholder="nombre@ejemplo.com"
                        @error('email') aria-invalid="true" aria-describedby="alta-errores" @enderror
                        class="{{ $claseInput }}"
                    />
                </div>

                <div class="flex flex-col gap-2">
                    <label for="password" class="text-sm font-medium text-neutral-800">{{ __('Contraseña') }}</label>
                    <input
                        id="password"
                        type="password"
                        wire:model="password"
                        required
                        autocomplete="new-password"
                        @error('password') aria-invalid="true" aria-describedby="alta-errores" @enderror
                        class="{{ $claseInput }}"
                    />
                </div>

                <div class="flex flex-col gap-2">
                    <label for="rol" class="text-sm font-medium text-neutral-800">{{ __('Rol') }}</label>
                    <select
                        id="rol"
                        wire:model="rol"
                        required
                        @error('rol') aria-invalid="true" aria-describedby="alta-errores" @enderror
                        class="{{ $claseInput }}"
                    >
                        <option value="">{{ __('Seleccioná un rol') }}</option>
                        <option value="moderador">{{ __('Moderador') }}</option>
                        <option value="admin">{{ __('Admin') }}</option>
                    </select>
                </div>

                <button
                    type="submit"
                    wire:loading.attr="disabled"
                    data-test="crear-staff-button"
                    class="mt-2 w-full rounded-md bg-[#8a5a1f] px-4 py-2.5 font-medium text-white transition hover:bg-[#744b19] focus:outline-none focus-visible:ring-2 focus-visible:ring-[#8a5a1f] focus-visible:ring-offset-2 disabled:opacity-60"
                >
                    {{ __('Crear cuenta') }}
                </button>
            </form>
        </section>
    </div>
</div>
