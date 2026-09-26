<?php

use App\Services\Analisis\Enmascarador;

test('it hides emails', function () {
    expect(Enmascarador::texto('Escribime a juan.perez@correo.com.ar para cobrar'))
        ->toBe('Escribime a [email] para cobrar');
});

test('it hides phone numbers written in different ways', function (string $telefono) {
    expect(Enmascarador::texto("Llamá al {$telefono} hoy"))->toBe('Llamá al [teléfono] hoy');
})->with(['+54 9 370 412-3456', '3704 123456', '(0370) 4123456', '4423-1234']);

test('it hides cards, CBUs and DNIs', function (string $numero) {
    expect(Enmascarador::texto("Dato: {$numero}."))->toBe('Dato: [número].');
})->with([
    'card' => '4509 9535 6623 3704',
    'cbu' => '0170099220000067797370',
    'dni' => '30.123.456',
]);

test('it keeps short numbers like times and amounts', function () {
    expect(Enmascarador::texto('Pagá $150.000 antes de las 18 hs del 12/05'))
        ->toBe('Pagá $150.000 antes de las 18 hs del 12/05');
});

test('it keeps IP addresses in links, since they are not personal data', function () {
    expect(Enmascarador::link('http://192.168.10.5/pago'))->toBe('http://192.168.10.5/pago');
});

test('it removes query strings and fragments from links', function () {
    expect(Enmascarador::link('https://docs.ejemplo.com/d/abc?token=secreto&u=juan#seccion'))
        ->toBe('https://docs.ejemplo.com/d/abc');
});

test('it removes query strings from links inside a text', function () {
    expect(Enmascarador::texto('Entrá a https://ejemplo.com/reset?token=123abc ya mismo'))
        ->toBe('Entrá a https://ejemplo.com/reset ya mismo');
});

test('it hides phone numbers inside links, like whatsapp ones', function () {
    expect(Enmascarador::link('https://wa.me/5493704123456'))->toBe('https://wa.me/[teléfono]');
});

test('it truncates long content', function () {
    $texto = Enmascarador::texto(str_repeat('palabra ', 40));
    $link = Enmascarador::link('https://ejemplo.com/'.str_repeat('a', 200));

    expect(mb_strlen($texto))->toBeLessThanOrEqual(Enmascarador::LARGO_TEXTO + 1)
        ->and($texto)->toEndWith('…')
        ->and(mb_strlen($link))->toBeLessThanOrEqual(Enmascarador::LARGO_LINK + 1)
        ->and($link)->toEndWith('…');
});

test('content uses link rules for a single link and text rules otherwise', function () {
    expect(Enmascarador::contenido('https://ejemplo.com/a?token=1'))->toBe('https://ejemplo.com/a')
        ->and(Enmascarador::contenido("BEGIN:VCARD\nTEL:3704123456\nEND:VCARD"))->toBe('BEGIN:VCARD TEL:[teléfono] END:VCARD');
});
