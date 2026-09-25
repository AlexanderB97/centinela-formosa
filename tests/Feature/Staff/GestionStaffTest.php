<?php

use App\Enums\RolStaff;
use App\Models\UsuarioStaff;
use Livewire\Livewire;

test('staff dashboard redirects guests to the staff login', function () {
    $this->get(route('staff.dashboard'))
        ->assertRedirect(route('staff.login'));
});

test('staff dashboard greets the authenticated staff member by name', function () {
    $this->actingAs(UsuarioStaff::factory()->create(['nombre' => 'Ana Pérez']), 'staff');

    $this->get(route('staff.dashboard'))
        ->assertOk()
        ->assertSee('Bienvenido/a, Ana Pérez');
});

test('gestion de staff link is hidden for moderators', function () {
    $this->actingAs(UsuarioStaff::factory()->create(), 'staff');

    $this->get(route('staff.dashboard'))
        ->assertOk()
        ->assertDontSee('Gestión de staff');
});

test('gestion de staff link is shown for admins', function () {
    $this->actingAs(UsuarioStaff::factory()->admin()->create(), 'staff');

    $this->get(route('staff.dashboard'))
        ->assertOk()
        ->assertSee('Gestión de staff');
});

test('staff usuarios page can be rendered for admins with the real accounts', function () {
    $admin = UsuarioStaff::factory()->admin()->create(['nombre' => 'Laura Giménez']);
    UsuarioStaff::factory()->create(['nombre' => 'Martín Acosta', 'email' => 'martin.acosta@centinela.test']);

    $this->actingAs($admin, 'staff');

    $this->get(route('staff.usuarios'))
        ->assertOk()
        ->assertSee('Gestión de staff')
        ->assertSee('Laura Giménez')
        ->assertSee('martin.acosta@centinela.test')
        ->assertSee('Moderador');
});

test('staff listing never includes passwords or hashes', function () {
    $admin = UsuarioStaff::factory()->admin()->create(['password' => 'secreto-del-admin']);
    $this->actingAs($admin, 'staff');

    $html = $this->get(route('staff.usuarios'))->assertOk()->getContent();

    expect($html)->not->toContain('secreto-del-admin')
        ->not->toContain($admin->getAuthPassword());

    $cuentas = Livewire::test('pages::staff.usuarios')->instance()->cuentas;

    foreach ($cuentas as $cuenta) {
        expect(array_keys($cuenta->getAttributes()))->toEqual(['id', 'nombre', 'email', 'rol']);
    }
});

test('creating a staff account adds a row to the table and persists it', function () {
    $this->actingAs(UsuarioStaff::factory()->admin()->create(), 'staff');

    Livewire::test('pages::staff.usuarios')
        ->set('nombre', 'Nuevo Moderador')
        ->set('email', '  Nuevo@Centinela.test ')
        ->set('password', 'secreto-123')
        ->set('rol', 'moderador')
        ->call('crear')
        ->assertHasNoErrors()
        ->assertSee('nuevo@centinela.test')
        ->assertSee('Cuenta de Nuevo Moderador creada.')
        ->assertDontSee('secreto-123')
        ->assertSet('password', '');

    $creado = UsuarioStaff::where('email', 'nuevo@centinela.test')->sole();

    expect($creado->nombre)->toBe('Nuevo Moderador')
        ->and($creado->rol)->toBe(RolStaff::Moderador)
        ->and($creado->password)->not->toBe('secreto-123');
});

test('duplicate email shows a validation error', function () {
    $admin = UsuarioStaff::factory()->admin()->create(['email' => 'laura.gimenez@centinela.test']);
    $this->actingAs($admin, 'staff');

    Livewire::test('pages::staff.usuarios')
        ->set('nombre', 'Duplicado')
        ->set('email', 'Laura.Gimenez@centinela.test')
        ->set('password', 'secreto-123')
        ->set('rol', 'admin')
        ->call('crear')
        ->assertHasErrors(['email' => 'unique'])
        ->assertSee('Ya existe una cuenta con ese email.');

    expect(UsuarioStaff::count())->toBe(1);
});

test('moderators cannot use the staff management component', function () {
    $this->actingAs(UsuarioStaff::factory()->create(), 'staff');

    Livewire::test('pages::staff.usuarios')
        ->set('nombre', 'Intruso')
        ->set('email', 'intruso@centinela.test')
        ->set('password', 'secreto-123')
        ->set('rol', 'admin')
        ->call('crear')
        ->assertForbidden();

    expect(UsuarioStaff::where('email', 'intruso@centinela.test')->exists())->toBeFalse();
});
