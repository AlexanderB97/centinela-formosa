<?php

test('returns a successful response', function () {
    $response = $this->get(route('home'));

    $response->assertOk();
});

test('the site root shows the analizador', function () {
    $this->get(route('home'))
        ->assertOk()
        ->assertSeeLivewire('pages::analizador')
        ->assertSee('Analizador de riesgo');

    expect(route('home'))->not->toBe(route('analizar'));
    $this->get(route('analizar'))->assertOk()->assertSeeLivewire('pages::analizador');
});
