<?php

use App\Enums\EstadoNoticia;
use App\Enums\RolStaff;
use App\Models\Noticia;
use App\Models\UsuarioStaff;
use App\Services\PublicarNoticia;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

function staffDeNoticias(RolStaff $rol = RolStaff::Moderador): UsuarioStaff
{
    $staff = UsuarioStaff::factory()->create(['rol' => $rol]);
    test()->actingAs($staff, 'staff');

    return $staff;
}

test('visitors are sent to the staff login from every news page', function (string $ruta) {
    $noticia = Noticia::factory()->create();

    $this->get(route($ruta, $ruta === 'staff.noticias.editar' ? $noticia : []))
        ->assertRedirect(route('staff.login'));
})->with(['staff.noticias', 'staff.noticias.crear', 'staff.noticias.editar']);

test('any staff member can manage news, moderator or admin', function (RolStaff $rol) {
    staffDeNoticias($rol);
    $noticia = Noticia::factory()->create();

    $this->get(route('staff.noticias'))->assertOk()->assertSee('Nueva noticia');
    $this->get(route('staff.noticias.crear'))->assertOk()->assertSee('Guardar borrador');
    $this->get(route('staff.noticias.editar', $noticia))->assertOk()->assertSee('Guardar cambios');
})->with([RolStaff::Moderador, RolStaff::Admin]);

test('the staff sidebar shows the news link to moderators too', function () {
    staffDeNoticias(RolStaff::Moderador);

    $this->get(route('staff.dashboard'))
        ->assertSee(route('staff.noticias'))
        ->assertDontSee(route('staff.usuarios'));
});

test('the Livewire actions refuse requests without a staff session', function () {
    $noticia = Noticia::factory()->create();

    Livewire::test('pages::staff.noticias')->call('publicar', $noticia->id)->assertForbidden();
    Livewire::test('pages::staff.editar-noticia')->set('titulo', 'x')->set('cuerpo', 'y')->call('guardar')->assertForbidden();

    expect($noticia->refresh()->estado)->toBe(EstadoNoticia::Borrador)
        ->and(Noticia::count())->toBe(1);
});

test('a new news is saved as a draft with its author and no date', function () {
    $staff = staffDeNoticias();

    Livewire::test('pages::staff.editar-noticia')
        ->set('titulo', '  Cuidado con el falso aviso del banco  ')
        ->set('cuerpo', "Primer párrafo.\n\nSegundo párrafo.")
        ->call('guardar')
        ->assertHasNoErrors()
        ->assertRedirect(route('staff.noticias'));

    $noticia = Noticia::sole();

    expect($noticia->titulo)->toBe('Cuidado con el falso aviso del banco')
        ->and($noticia->estado)->toBe(EstadoNoticia::Borrador)
        ->and($noticia->publicada_en)->toBeNull()
        ->and($noticia->usuario_staff_id)->toBe($staff->id)
        ->and($noticia->imagen_ruta)->toBeNull();
});

test('title and body are required and limited', function () {
    staffDeNoticias();

    Livewire::test('pages::staff.editar-noticia')
        ->call('guardar')
        ->assertHasErrors(['titulo' => 'required', 'cuerpo' => 'required'])
        ->assertSee('El título es obligatorio.');

    Livewire::test('pages::staff.editar-noticia')
        ->set('titulo', str_repeat('a', 161))
        ->set('cuerpo', 'texto')
        ->call('guardar')
        ->assertHasErrors(['titulo' => 'max']);

    expect(Noticia::count())->toBe(0);
});

test('editing keeps the state and the author, and warns when the news is published', function () {
    $autor = UsuarioStaff::factory()->create();
    $noticia = Noticia::factory()->publicada()->for($autor, 'autor')->create(['titulo' => 'Título viejo']);
    staffDeNoticias();

    Livewire::test('pages::staff.editar-noticia', ['noticia' => $noticia])
        ->assertSet('titulo', 'Título viejo')
        ->assertSeeHtml('data-test="aviso-publicada"')
        ->set('titulo', 'Título nuevo')
        ->call('guardar')
        ->assertHasNoErrors();

    $noticia->refresh();

    expect($noticia->titulo)->toBe('Título nuevo')
        ->and($noticia->estado)->toBe(EstadoNoticia::Publicada)
        ->and($noticia->usuario_staff_id)->toBe($autor->id);
});

test('the list separates drafts from published news and shows the author only to staff', function () {
    staffDeNoticias();
    $autor = UsuarioStaff::factory()->create(['nombre' => 'Laura Giménez']);
    Noticia::factory()->for($autor, 'autor')->create(['titulo' => 'Un borrador']);
    Noticia::factory()->publicada()->for($autor, 'autor')->create(['titulo' => 'Una publicada']);

    Livewire::test('pages::staff.noticias')
        ->assertSeeInOrder(['Borradores', 'Un borrador', 'Publicadas', 'Una publicada'])
        ->assertSee('Laura Giménez');
});

test('publishing sets the state and the date; unpublishing keeps the date', function () {
    staffDeNoticias();
    $noticia = Noticia::factory()->create();

    $this->travelTo('2026-09-20 15:00:00');
    Livewire::test('pages::staff.noticias')
        ->call('publicar', $noticia->id)
        ->assertSet('tipoAviso', 'exito');

    expect($noticia->refresh()->estado)->toBe(EstadoNoticia::Publicada)
        ->and($noticia->publicada_en->toDateTimeString())->toBe('2026-09-20 15:00:00');

    $this->travelTo('2026-09-25 10:00:00');
    Livewire::test('pages::staff.noticias')->call('despublicar', $noticia->id)->assertSet('tipoAviso', 'exito');

    expect($noticia->refresh()->estado)->toBe(EstadoNoticia::Borrador)
        ->and($noticia->publicada_en->toDateTimeString())->toBe('2026-09-20 15:00:00');

    // Publicar de nuevo no cambia la fecha: una corrección no salta al primer lugar.
    app(PublicarNoticia::class)->publicar($noticia);

    expect($noticia->refresh()->publicada_en->toDateTimeString())->toBe('2026-09-20 15:00:00');
});

test('publishing twice (or from a stale page) is a calm conflict, not an error', function () {
    staffDeNoticias();
    $noticia = Noticia::factory()->publicada()->create();

    expect(fn () => app(PublicarNoticia::class)->publicar($noticia))->toThrow(ValidationException::class);

    Livewire::test('pages::staff.noticias')
        ->call('publicar', $noticia->id)
        ->assertSet('tipoAviso', 'conflicto')
        ->assertSee("La noticia #{$noticia->id} ya está publicada.");

    Livewire::test('pages::staff.noticias')
        ->call('despublicar', Noticia::factory()->create()->id)
        ->assertSet('tipoAviso', 'conflicto');

    Livewire::test('pages::staff.noticias')
        ->call('publicar', 999)
        ->assertSet('tipoAviso', 'conflicto')
        ->assertSee('No encontramos la noticia #999.');
});
