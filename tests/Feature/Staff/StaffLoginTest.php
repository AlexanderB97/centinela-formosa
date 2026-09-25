<?php

use App\Enums\RolStaff;
use App\Models\User;
use App\Models\UsuarioStaff;
use Database\Seeders\UsuarioStaffSeeder;

test('staff login screen can be rendered', function () {
    $this->get(route('staff.login'))->assertOk();
});

test('staff can authenticate and are redirected to the staff dashboard', function (RolStaff $rol) {
    $usuario = UsuarioStaff::factory()->create(['rol' => $rol]);

    $response = $this->post(route('staff.login.store'), [
        'email' => $usuario->email,
        'password' => 'password',
    ]);

    $response->assertSessionHasNoErrors()->assertRedirect(route('staff.dashboard'));
    $this->assertAuthenticatedAs($usuario, 'staff');
})->with(RolStaff::cases());

test('failed login shows the same error whether or not the email exists', function () {
    $usuario = UsuarioStaff::factory()->create();

    $this->post(route('staff.login.store'), [
        'email' => $usuario->email,
        'password' => 'wrong-password',
    ])->assertSessionHasErrors(['email' => __('auth.failed')]);

    $this->post(route('staff.login.store'), [
        'email' => 'nobody@example.com',
        'password' => 'wrong-password',
    ])->assertSessionHasErrors(['email' => __('auth.failed')]);

    $this->assertGuest('staff');
});

test('login requires a valid email and a password', function () {
    $this->post(route('staff.login.store'), [
        'email' => 'not-an-email',
        'password' => '',
    ])->assertSessionHasErrors(['email', 'password']);

    $this->assertGuest('staff');
});

test('login is throttled after too many failed attempts', function () {
    $usuario = UsuarioStaff::factory()->create();

    foreach (range(1, 5) as $intento) {
        $this->post(route('staff.login.store'), ['email' => $usuario->email, 'password' => 'wrong-password']);
    }

    $this->post(route('staff.login.store'), [
        'email' => $usuario->email,
        'password' => 'password',
    ])->assertSessionHasErrors('email');

    $this->assertGuest('staff');
});

test('protected staff routes redirect guests to the staff login', function () {
    $this->get(route('staff.dashboard'))->assertRedirect(route('staff.login'));
    $this->post(route('staff.logout'))->assertRedirect(route('staff.login'));
});

test('public users can not access protected staff routes', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('staff.dashboard'))
        ->assertRedirect(route('staff.login'));
});

test('authenticated staff can view the dashboard', function () {
    $this->actingAs(UsuarioStaff::factory()->create(), 'staff')
        ->get(route('staff.dashboard'))
        ->assertOk();
});

test('authenticated staff visiting the login are redirected to the dashboard', function () {
    $this->actingAs(UsuarioStaff::factory()->create(), 'staff')
        ->get(route('staff.login'))
        ->assertRedirect(route('staff.dashboard'));
});

test('staff can log out', function () {
    $this->actingAs(UsuarioStaff::factory()->create(), 'staff')
        ->post(route('staff.logout'))
        ->assertRedirect(route('staff.login'));

    $this->assertGuest('staff');
});

test('the home page does not link to the staff login', function () {
    $this->get(route('home'))->assertDontSee('/staff', escape: false);
});

it('the seeder creates one moderator and one admin with the known test password, resetting it on re-run', function () {
    $this->seed(UsuarioStaffSeeder::class);
    $this->seed(UsuarioStaffSeeder::class);

    expect(UsuarioStaff::pluck('rol')->all())->toEqualCanonicalizing([RolStaff::Moderador, RolStaff::Admin])
        ->and(Hash::check('centinela2026', UsuarioStaff::where('email', 'admin@centinela-formosa.test')->first()->password))->toBeTrue()
        ->and(Hash::check('centinela2026', UsuarioStaff::where('email', 'moderador@centinela-formosa.test')->first()->password))->toBeTrue();
});
