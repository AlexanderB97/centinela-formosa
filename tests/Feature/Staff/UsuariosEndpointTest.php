<?php

use App\Enums\RolStaff;
use App\Models\User;
use App\Models\UsuarioStaff;

function datosDeCuenta(array $cambios = []): array
{
    return array_merge([
        'nombre' => 'Nueva Cuenta',
        'email' => 'nueva@centinela.test',
        'password' => 'secreto-123',
        'rol' => 'moderador',
    ], $cambios);
}

test('moderators get 403, not 404, on the staff management routes', function () {
    $this->actingAs(UsuarioStaff::factory()->create(), 'staff');

    $this->get(route('staff.usuarios'))->assertForbidden();
    $this->postJson(route('staff.usuarios.store'), datosDeCuenta())->assertForbidden();

    expect(UsuarioStaff::count())->toBe(1);
});

test('guests are redirected to the staff login', function () {
    $this->get(route('staff.usuarios'))->assertRedirect(route('staff.login'));
    $this->post(route('staff.usuarios.store'), datosDeCuenta())->assertRedirect(route('staff.login'));
});

test('users of the default web guard are not staff admins', function () {
    $this->actingAs(User::factory()->create());

    $this->get(route('staff.usuarios'))->assertRedirect(route('staff.login'));
});

test('admins can create accounts with either role', function (string $rol) {
    $this->actingAs(UsuarioStaff::factory()->admin()->create(), 'staff');

    $respuesta = $this->postJson(route('staff.usuarios.store'), datosDeCuenta(['rol' => $rol]))
        ->assertCreated()
        ->assertJson(['nombre' => 'Nueva Cuenta', 'email' => 'nueva@centinela.test', 'rol' => $rol]);

    expect(array_keys($respuesta->json()))->toEqualCanonicalizing(['id', 'nombre', 'email', 'rol'])
        ->and($respuesta->getContent())->not->toContain('secreto-123');

    $creado = UsuarioStaff::where('email', 'nueva@centinela.test')->sole();

    expect($creado->rol)->toBe(RolStaff::from($rol))
        ->and($respuesta->json('id'))->toBe($creado->id);
})->with(['moderador', 'admin']);

test('the email is stored trimmed and lowercased', function () {
    $this->actingAs(UsuarioStaff::factory()->admin()->create(), 'staff');

    $this->postJson(route('staff.usuarios.store'), datosDeCuenta(['email' => '  Nueva@Centinela.TEST ']))
        ->assertCreated()
        ->assertJsonPath('email', 'nueva@centinela.test');
});

test('a duplicate email is a validation error, whatever its case', function () {
    $this->actingAs(UsuarioStaff::factory()->admin()->create(), 'staff');
    UsuarioStaff::factory()->create(['email' => 'nueva@centinela.test']);

    $this->postJson(route('staff.usuarios.store'), datosDeCuenta(['email' => 'NUEVA@centinela.test']))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['email' => 'Ya existe una cuenta con ese email.']);

    expect(UsuarioStaff::count())->toBe(2);
});

test('it validates the request', function (array $cambios, string $campo) {
    $this->actingAs(UsuarioStaff::factory()->admin()->create(), 'staff');

    $this->postJson(route('staff.usuarios.store'), datosDeCuenta($cambios))
        ->assertUnprocessable()
        ->assertJsonValidationErrors($campo);

    expect(UsuarioStaff::count())->toBe(1);
})->with([
    'missing name' => [['nombre' => ''], 'nombre'],
    'invalid email' => [['email' => 'no-es-un-email'], 'email'],
    'short password' => [['password' => '1234567'], 'password'],
    'invalid role' => [['rol' => 'superadmin'], 'rol'],
    'missing role' => [['rol' => null], 'rol'],
]);

test('a newly created account can log in right away', function () {
    $this->actingAs(UsuarioStaff::factory()->admin()->create(), 'staff');

    $this->postJson(route('staff.usuarios.store'), datosDeCuenta())->assertCreated();

    auth('staff')->logout();

    $this->post(route('staff.login.store'), ['email' => 'nueva@centinela.test', 'password' => 'secreto-123'])
        ->assertRedirect(route('staff.dashboard'));

    $this->assertAuthenticatedAs(UsuarioStaff::where('email', 'nueva@centinela.test')->sole(), 'staff');

    $this->get(route('staff.dashboard'))->assertOk()->assertSee('Bienvenido/a, Nueva Cuenta');
});
