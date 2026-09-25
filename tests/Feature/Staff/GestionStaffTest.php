<?php

use App\Models\User;
use Livewire\Livewire;

test('staff dashboard can be rendered', function () {
    $this->get(route('staff.dashboard'))
        ->assertOk()
        ->assertSee('Bienvenido/a');
});

test('staff dashboard greets the authenticated user by name', function () {
    $this->actingAs(User::factory()->create(['name' => 'Ana Pérez']));

    $this->get(route('staff.dashboard'))
        ->assertOk()
        ->assertSee('Bienvenido/a, Ana Pérez');
});

test('gestion de staff link is hidden for non admin users', function () {
    $this->actingAs(User::factory()->create());

    $this->get(route('staff.dashboard'))
        ->assertOk()
        ->assertDontSee('Gestión de staff');
});

test('gestion de staff link is shown for admin users', function () {
    $user = User::factory()->create();
    $user->rol = 'admin';

    $this->actingAs($user);

    $this->get(route('staff.dashboard'))
        ->assertOk()
        ->assertSee('Gestión de staff');
});

test('staff usuarios page can be rendered', function () {
    $this->get(route('staff.usuarios'))
        ->assertOk()
        ->assertSee('Gestión de staff')
        ->assertSee('laura.gimenez@centinela.test');
});

test('staff mock data never includes passwords', function () {
    $usuarios = Livewire::test('pages::staff.usuarios')->get('usuarios');

    foreach ($usuarios as $usuario) {
        expect(array_keys($usuario))->toEqual(['name', 'email', 'rol']);
    }
});

test('creating a staff account adds a row to the table', function () {
    $component = Livewire::test('pages::staff.usuarios');
    $cantidadInicial = count($component->get('usuarios'));

    $component
        ->set('name', 'Nuevo Moderador')
        ->set('email', 'nuevo@centinela.test')
        ->set('password', 'secreto-123')
        ->set('rol', 'moderador')
        ->call('crear')
        ->assertHasNoErrors()
        ->assertSee('nuevo@centinela.test')
        ->assertSee('Cuenta de Nuevo Moderador creada.')
        ->assertDontSee('secreto-123')
        ->assertSet('password', '');

    expect($component->get('usuarios'))->toHaveCount($cantidadInicial + 1);
});

test('duplicate email shows a validation error', function () {
    $component = Livewire::test('pages::staff.usuarios');
    $cantidadInicial = count($component->get('usuarios'));

    $component
        ->set('name', 'Duplicado')
        ->set('email', 'laura.gimenez@centinela.test')
        ->set('password', 'secreto-123')
        ->set('rol', 'admin')
        ->call('crear')
        ->assertHasErrors(['email' => 'not_in'])
        ->assertSee('Ya existe una cuenta con ese email.');

    expect($component->get('usuarios'))->toHaveCount($cantidadInicial);
});
