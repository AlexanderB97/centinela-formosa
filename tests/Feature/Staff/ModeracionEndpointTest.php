<?php

use App\Enums\EstadoReporte;
use App\Enums\TipoContenido;
use App\Models\Analisis;
use App\Models\CasoConfirmado;
use App\Models\Reporte;
use App\Models\UsuarioStaff;
use App\Services\Analisis\Huella;
use App\Services\ModerarReporte;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

function reporteDe(string $contenido, TipoContenido $tipo = TipoContenido::Texto): Reporte
{
    return Reporte::factory()
        ->for(Analisis::factory()->create(['tipo' => $tipo, 'contenido' => $contenido]))
        ->create();
}

test('any staff member can confirm and discard, without 403', function (bool $esAdmin) {
    $staff = $esAdmin ? UsuarioStaff::factory()->admin()->create() : UsuarioStaff::factory()->create();
    $this->actingAs($staff, 'staff');

    $aConfirmar = reporteDe('Ganaste un premio, reclamalo ya');
    $aDescartar = reporteDe('Hola, nos vemos mañana');

    $this->postJson(route('staff.reportes.confirmar', $aConfirmar))
        ->assertOk()
        ->assertExactJson(['id' => $aConfirmar->id, 'estado' => 'confirmado']);

    $this->postJson(route('staff.reportes.descartar', $aDescartar))
        ->assertOk()
        ->assertExactJson(['id' => $aDescartar->id, 'estado' => 'descartado']);
})->with(['moderador' => false, 'admin' => true]);

test('guests are redirected to the staff login', function () {
    $reporte = Reporte::factory()->create();

    $this->post(route('staff.reportes.confirmar', $reporte))->assertRedirect(route('staff.login'));
    $this->post(route('staff.reportes.descartar', $reporte))->assertRedirect(route('staff.login'));

    expect($reporte->refresh()->estado)->toBe(EstadoReporte::Pendiente);
});

test('confirming creates a confirmed case from the linked analysis', function () {
    $this->actingAs(UsuarioStaff::factory()->create(), 'staff');
    $reporte = reporteDe('https://premios-formosa.top/reclamar', TipoContenido::Link);

    $this->postJson(route('staff.reportes.confirmar', $reporte))->assertOk();

    $caso = CasoConfirmado::sole();

    expect($reporte->refresh()->estado)->toBe(EstadoReporte::Confirmado)
        ->and($caso->tipo)->toBe(TipoContenido::Link)
        ->and($caso->contenido)->toBe('https://premios-formosa.top/reclamar');
});

test('confirming content that matches an existing case updates it instead of duplicating', function () {
    $this->actingAs(UsuarioStaff::factory()->create(), 'staff');
    $existente = CasoConfirmado::factory()->create(['tipo' => TipoContenido::Texto, 'contenido' => 'Hola, soy tu nieto, cambié de número']);
    $this->travel(5)->minutes();

    // Same fingerprint: case and spacing are normalized.
    $reporte = reporteDe("  hola, SOY tu nieto,\n cambié de número ");

    $this->postJson(route('staff.reportes.confirmar', $reporte))->assertOk();

    $caso = CasoConfirmado::sole();

    expect($caso->id)->toBe($existente->id)
        ->and($caso->huella)->toBe($existente->huella)
        ->and($caso->updated_at->greaterThan($existente->updated_at))->toBeTrue();
});

test('discarding does not touch confirmed cases', function () {
    $this->actingAs(UsuarioStaff::factory()->create(), 'staff');
    CasoConfirmado::factory()->create();
    $reporte = reporteDe('Ganaste un premio');

    $this->postJson(route('staff.reportes.descartar', $reporte))->assertOk();

    expect($reporte->refresh()->estado)->toBe(EstadoReporte::Descartado)
        ->and(CasoConfirmado::count())->toBe(1);
});

test('acting on a report that is no longer pending is a 422 and changes nothing', function (string $primera, string $segunda) {
    $this->actingAs(UsuarioStaff::factory()->create(), 'staff');
    $reporte = reporteDe('Ganaste un premio');

    $this->postJson(route("staff.reportes.{$primera}", $reporte))->assertOk();
    $estado = $reporte->refresh()->estado;
    $casos = CasoConfirmado::count();

    $this->postJson(route("staff.reportes.{$segunda}", $reporte))
        ->assertUnprocessable()
        ->assertJsonPath('message', "El reporte #{$reporte->id} ya no está pendiente: otro moderador ya lo resolvió.")
        ->assertJsonValidationErrors('reporte');

    expect($reporte->refresh()->estado)->toBe($estado)
        ->and(CasoConfirmado::count())->toBe($casos);
})->with([
    'confirm twice' => ['confirmar', 'confirmar'],
    'discard after confirm' => ['confirmar', 'descartar'],
    'confirm after discard' => ['descartar', 'confirmar'],
]);

test('acting on a report that does not exist is a 404', function (string $accion) {
    $this->actingAs(UsuarioStaff::factory()->create(), 'staff');

    $this->postJson(route("staff.reportes.{$accion}", 999))->assertNotFound();
})->with(['confirmar', 'descartar']);

test('two moderators confirming the same report at once create a single case', function () {
    $reporte = reporteDe('Ganaste un premio');

    // Both moderators loaded the report while it was still pending.
    $vistaDeUno = Reporte::find($reporte->id);
    $vistaDeOtro = Reporte::find($reporte->id);
    $moderar = app(ModerarReporte::class);

    $moderar->confirmar($vistaDeUno);

    expect(fn () => $moderar->confirmar($vistaDeOtro))->toThrow(ValidationException::class);
    expect(CasoConfirmado::count())->toBe(1)
        ->and($reporte->refresh()->estado)->toBe(EstadoReporte::Confirmado);
});

test('a stale discard after a confirmation cannot overwrite the confirmed state', function () {
    $reporte = reporteDe('Ganaste un premio');
    $vistaVieja = Reporte::find($reporte->id);

    app(ModerarReporte::class)->confirmar(Reporte::find($reporte->id));

    expect(fn () => app(ModerarReporte::class)->descartar($vistaVieja))->toThrow(ValidationException::class);
    expect($reporte->refresh()->estado)->toBe(EstadoReporte::Confirmado);
});

test('confirming two different reports with the same content keeps a single case', function () {
    $moderar = app(ModerarReporte::class);

    $moderar->confirmar(reporteDe('Ganaste un premio, reclamalo ya'));
    $moderar->confirmar(reporteDe('GANASTE un premio,   reclamalo ya'));

    expect(CasoConfirmado::count())->toBe(1)
        ->and(Reporte::where('estado', EstadoReporte::Confirmado->value)->count())->toBe(2);
});

test('if a concurrent confirmation inserts the same case first, the unique index is handled and nothing is duplicated', function () {
    $reporte = reporteDe('Ganaste un premio, reclamalo ya');
    $competidorInsertado = false;

    // Simulate the race: another request inserts the case between our lookup and our insert.
    CasoConfirmado::creating(function (CasoConfirmado $caso) use (&$competidorInsertado) {
        if (! $competidorInsertado) {
            $competidorInsertado = true;
            DB::table('casos_confirmados')->insert([
                'tipo' => 'texto',
                'contenido' => 'GANASTE un premio, reclamalo ya',
                'huella' => Huella::de('GANASTE un premio, reclamalo ya'),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    });

    app(ModerarReporte::class)->confirmar($reporte);

    expect($competidorInsertado)->toBeTrue()
        ->and(CasoConfirmado::count())->toBe(1)
        ->and(CasoConfirmado::sole()->contenido)->toBe('Ganaste un premio, reclamalo ya')
        ->and($reporte->refresh()->estado)->toBe(EstadoReporte::Confirmado);
});
