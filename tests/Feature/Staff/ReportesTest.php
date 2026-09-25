<?php

use App\Models\User;
use Livewire\Livewire;

function estadoDelReporte($component, int $id): string
{
    return collect($component->get('reportes'))->firstWhere('id', $id)['estado'];
}

test('reportes page can be rendered', function () {
    $this->get(route('staff.reportes'))
        ->assertOk()
        ->assertSee('Reportes pendientes')
        ->assertSee('Reporte #1')
        ->assertSee('Reporte #2')
        ->assertSee('Reporte #3');
});

test('each card shows the full linked analysis, not only the comment', function () {
    Livewire::test('pages::staff.reportes')
        // Reporte #1: texto, riesgo, con comentario.
        ->assertSee('tu cuenta bancaria fue suspendida')
        ->assertSee('Nivel: Riesgo')
        ->assertSee('Pide datos sensibles como claves o códigos.')
        ->assertSee('se hace pasar por un banco')
        ->assertSee('decía ser del banco')
        // Reporte #2: link, dudoso, sin comentario.
        ->assertSee('http://mi-cuenta-premios-oficial-2024.com/ingresar')
        ->assertSee('Nivel: Dudoso')
        ->assertSee('Sin comentario')
        // Reporte #3: qr, seguro.
        ->assertSee('Foto de QR')
        ->assertSee('Nivel: Seguro');
});

test('reported links are never rendered as clickable anchors', function () {
    $html = Livewire::test('pages::staff.reportes')->html();

    expect($html)->not->toContain('href="http://mi-cuenta-premios-oficial-2024.com');
    expect($html)->not->toContain('href="https://carta.bar-ejemplo.com.ar');
});

test('reportes link is visible for any staff role, without role condition', function (?string $rol) {
    if ($rol !== null) {
        $user = User::factory()->create();
        $user->rol = $rol;
        $this->actingAs($user);
    }

    $this->get(route('staff.dashboard'))
        ->assertOk()
        ->assertSee('href="'.route('staff.reportes').'"', false);
})->with(['moderador' => 'moderador', 'admin' => 'admin', 'sin sesion' => null]);

test('confirming a report removes its card but keeps the record', function () {
    $component = Livewire::test('pages::staff.reportes')
        ->call('confirmar', 1)
        ->assertSet('tipoAviso', 'exito')
        ->assertSee('Reporte #1 confirmado.')
        ->assertDontSee('data-reporte="1"', false)
        ->assertSee('data-reporte="2"', false);

    expect($component->get('reportes'))->toHaveCount(3);
    expect(estadoDelReporte($component, 1))->toBe('confirmado');
});

test('discarding a report removes its card but keeps the record', function () {
    $component = Livewire::test('pages::staff.reportes')
        ->call('descartar', 2)
        ->assertSet('tipoAviso', 'exito')
        ->assertSee('Reporte #2 descartado.')
        ->assertDontSee('data-reporte="2"', false);

    expect($component->get('reportes'))->toHaveCount(3);
    expect(estadoDelReporte($component, 2))->toBe('descartado');
});

test('acting twice on the same report shows a clear no longer pending notice', function () {
    $component = Livewire::test('pages::staff.reportes')
        ->call('confirmar', 1)
        ->call('descartar', 1)
        ->assertSet('tipoAviso', 'conflicto')
        ->assertSee('El reporte #1 ya no está pendiente')
        ->assertSee('data-aviso="conflicto"', false)
        ->assertDontSee('bg-red-50', false);

    expect(estadoDelReporte($component, 1))->toBe('confirmado');
});

test('report resolved by another moderator returns the conflict and leaves the queue', function () {
    $component = Livewire::test('pages::staff.reportes')
        ->assertSee('data-reporte="3"', false)
        ->call('descartar', 3)
        ->assertSet('tipoAviso', 'conflicto')
        ->assertSee('otro moderador ya lo resolvió')
        ->assertDontSee('data-reporte="3"', false);

    expect(estadoDelReporte($component, 3))->not->toBe('descartado');
});

test('unknown report id shows a notice without breaking the page', function () {
    Livewire::test('pages::staff.reportes')
        ->call('confirmar', 999)
        ->assertSet('tipoAviso', 'conflicto')
        ->assertSee('No encontramos el reporte #999.')
        ->assertSee('data-reporte="1"', false);
});

test('empty state is shown when there is nothing left to review', function () {
    Livewire::test('pages::staff.reportes')
        ->assertDontSee('No hay nada para revisar')
        ->call('confirmar', 1)
        ->call('descartar', 2)
        ->call('confirmar', 3)
        ->assertSee('No hay nada para revisar')
        ->assertSee('Reportes pendientes')
        ->assertSee('(0)');
});
