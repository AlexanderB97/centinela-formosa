<?php

use App\Enums\EstadoReporte;
use App\Enums\NivelRiesgo;
use App\Enums\TipoContenido;
use App\Models\Analisis;
use App\Models\CasoConfirmado;
use App\Models\Reporte;
use App\Models\UsuarioStaff;
use App\Services\ModerarReporte;
use Livewire\Livewire;

beforeEach(function () {
    $this->actingAs(UsuarioStaff::factory()->create(), 'staff');
});

/**
 * Three pending reports with the same variety the frontend mock had.
 *
 * @return array{0: Reporte, 1: Reporte, 2: Reporte}
 */
function reportesDeEjemplo(): array
{
    $crear = fn (array $analisis, ?string $comentario) => Reporte::factory()
        ->for(Analisis::factory()->create($analisis))
        ->create(['comentario' => $comentario]);

    return [
        $crear([
            'tipo' => TipoContenido::Texto,
            'contenido' => "URGENTE: tu cuenta bancaria fue suspendida.\nPara reactivarla respondé con tu clave.",
            'nivel' => NivelRiesgo::Riesgo,
            'razones' => ['Menciona una entidad bancaria o datos de cuenta.', 'Pide datos sensibles como claves o códigos.'],
            'explicacion' => 'El mensaje se hace pasar por un banco y pide la clave.',
        ], 'Me llegó por WhatsApp, decía ser del banco.'),
        $crear([
            'tipo' => TipoContenido::Link,
            'contenido' => 'http://mi-cuenta-premios-oficial-2024.com/ingresar',
            'nivel' => NivelRiesgo::Dudoso,
            'razones' => ['El enlace no usa conexión segura (https).'],
            'explicacion' => 'El dominio tiene características que suelen usarse para imitar sitios oficiales.',
        ], null),
        $crear([
            'tipo' => TipoContenido::Qr,
            'contenido' => 'https://carta.bar-ejemplo.com.ar/menu',
            'nivel' => NivelRiesgo::Seguro,
            'razones' => ['No se detectaron patrones típicos de estafa.'],
            'explicacion' => 'No encontramos señales de riesgo en el contenido del código QR.',
        ], 'El QR estaba pegado sobre la carta de un bar.'),
    ];
}

test('reportes page can be rendered for any staff role', function (bool $esAdmin) {
    $staff = $esAdmin ? UsuarioStaff::factory()->admin()->create() : UsuarioStaff::factory()->create();
    $this->actingAs($staff, 'staff');
    [$uno, $dos, $tres] = reportesDeEjemplo();

    $this->get(route('staff.reportes'))
        ->assertOk()
        ->assertSee('Reportes pendientes')
        ->assertSee("Reporte #{$uno->id}")
        ->assertSee("Reporte #{$dos->id}")
        ->assertSee("Reporte #{$tres->id}");
})->with(['moderador' => false, 'admin' => true]);

test('guests are redirected to the staff login', function () {
    auth('staff')->logout();

    $this->get(route('staff.reportes'))->assertRedirect(route('staff.login'));
});

test('only pending reports are listed', function () {
    $pendiente = Reporte::factory()->create();
    $confirmado = Reporte::factory()->create(['estado' => EstadoReporte::Confirmado]);
    $descartado = Reporte::factory()->create(['estado' => EstadoReporte::Descartado]);

    Livewire::test('pages::staff.reportes')
        ->assertSeeHtml("data-reporte=\"{$pendiente->id}\"")
        ->assertDontSeeHtml("data-reporte=\"{$confirmado->id}\"")
        ->assertDontSeeHtml("data-reporte=\"{$descartado->id}\"")
        ->assertSee('(1)');
});

test('each card shows the full linked analysis, not only the comment', function () {
    reportesDeEjemplo();

    Livewire::test('pages::staff.reportes')
        // Texto, riesgo, con comentario.
        ->assertSee('tu cuenta bancaria fue suspendida')
        ->assertSee('Nivel: Riesgo')
        ->assertSee('Pide datos sensibles como claves o códigos.')
        ->assertSee('se hace pasar por un banco')
        ->assertSee('decía ser del banco')
        // Link, dudoso, sin comentario.
        ->assertSee('http://mi-cuenta-premios-oficial-2024.com/ingresar')
        ->assertSee('Nivel: Dudoso')
        ->assertSee('Sin comentario')
        // QR, seguro.
        ->assertSee('Foto de QR')
        ->assertSee('Nivel: Seguro');
});

test('reported links are never rendered as clickable anchors', function () {
    reportesDeEjemplo();

    $html = Livewire::test('pages::staff.reportes')->html();

    expect($html)->not->toContain('href="http://mi-cuenta-premios-oficial-2024.com');
    expect($html)->not->toContain('href="https://carta.bar-ejemplo.com.ar');
});

test('reportes link is visible for any staff role, without role condition', function (bool $esAdmin) {
    $staff = $esAdmin ? UsuarioStaff::factory()->admin()->create() : UsuarioStaff::factory()->create();
    $this->actingAs($staff, 'staff');

    $this->get(route('staff.dashboard'))
        ->assertOk()
        ->assertSee('href="'.route('staff.reportes').'"', false);
})->with(['moderador' => false, 'admin' => true]);

test('confirming a report removes its card, keeps the record and creates the confirmed case', function () {
    [$uno, $dos] = reportesDeEjemplo();

    Livewire::test('pages::staff.reportes')
        ->call('confirmar', $uno->id)
        ->assertSet('tipoAviso', 'exito')
        ->assertSee("Reporte #{$uno->id} confirmado.")
        ->assertDontSeeHtml("data-reporte=\"{$uno->id}\"")
        ->assertSeeHtml("data-reporte=\"{$dos->id}\"");

    expect(Reporte::count())->toBe(3)
        ->and($uno->refresh()->estado)->toBe(EstadoReporte::Confirmado)
        ->and(CasoConfirmado::sole()->contenido)->toBe($uno->analisis->contenido);
});

test('discarding a report removes its card, keeps the record and creates no case', function () {
    [, $dos] = reportesDeEjemplo();

    Livewire::test('pages::staff.reportes')
        ->call('descartar', $dos->id)
        ->assertSet('tipoAviso', 'exito')
        ->assertSee("Reporte #{$dos->id} descartado.")
        ->assertDontSeeHtml("data-reporte=\"{$dos->id}\"");

    expect(Reporte::count())->toBe(3)
        ->and($dos->refresh()->estado)->toBe(EstadoReporte::Descartado)
        ->and(CasoConfirmado::count())->toBe(0);
});

test('acting twice on the same report shows a clear no longer pending notice', function () {
    [$uno] = reportesDeEjemplo();

    Livewire::test('pages::staff.reportes')
        ->call('confirmar', $uno->id)
        ->call('descartar', $uno->id)
        ->assertSet('tipoAviso', 'conflicto')
        ->assertSee("El reporte #{$uno->id} ya no está pendiente")
        ->assertSeeHtml('data-aviso="conflicto"')
        ->assertDontSeeHtml('bg-red-50');

    expect($uno->refresh()->estado)->toBe(EstadoReporte::Confirmado)
        ->and(CasoConfirmado::count())->toBe(1);
});

test('report resolved by another moderator returns the conflict and leaves the queue', function () {
    [, , $tres] = reportesDeEjemplo();

    $component = Livewire::test('pages::staff.reportes')
        ->assertSeeHtml("data-reporte=\"{$tres->id}\"");

    // Otro moderador lo confirma mientras esta cola sigue abierta.
    app(ModerarReporte::class)->confirmar(Reporte::find($tres->id));

    $component->call('descartar', $tres->id)
        ->assertSet('tipoAviso', 'conflicto')
        ->assertSee('otro moderador ya lo resolvió')
        ->assertSee('Lo sacamos de tu cola.')
        ->assertDontSeeHtml("data-reporte=\"{$tres->id}\"");

    expect($tres->refresh()->estado)->toBe(EstadoReporte::Confirmado);
});

test('unknown report id shows a notice without breaking the page', function () {
    [$uno] = reportesDeEjemplo();

    Livewire::test('pages::staff.reportes')
        ->call('confirmar', 999)
        ->assertSet('tipoAviso', 'conflicto')
        ->assertSee('No encontramos el reporte #999.')
        ->assertSeeHtml("data-reporte=\"{$uno->id}\"");
});

test('empty state is shown when there is nothing left to review', function () {
    [$uno, $dos, $tres] = reportesDeEjemplo();

    Livewire::test('pages::staff.reportes')
        ->assertDontSee('No hay nada para revisar')
        ->call('confirmar', $uno->id)
        ->call('descartar', $dos->id)
        ->call('confirmar', $tres->id)
        ->assertSee('No hay nada para revisar')
        ->assertSee('Reportes pendientes')
        ->assertSee('(0)');
});
