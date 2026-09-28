<?php

use App\Enums\Barrio;
use App\Enums\Departamento;
use App\Enums\MedioRecepcion;

test('the nine departments of Formosa have their exact labels', function () {
    expect(array_map(fn (Departamento $departamento) => $departamento->etiqueta(), Departamento::cases()))->toBe([
        'Formosa Capital', 'Pilcomayo', 'Laishí', 'Pirané', 'Patiño', 'Pilagás', 'Bermejo', 'Matacos', 'Ramón Lista',
    ]);
});

test('the four channels have their exact labels', function () {
    expect(array_map(fn (MedioRecepcion $medio) => $medio->etiqueta(), MedioRecepcion::cases()))->toBe([
        'WhatsApp', 'Email', 'SMS', 'Redes sociales',
    ]);
});

test('stored values are plain identifiers without accents, spaces or uppercase', function () {
    $valores = [...array_column(Departamento::cases(), 'value'), ...array_column(MedioRecepcion::cases(), 'value'), ...array_column(Barrio::cases(), 'value')];

    foreach ($valores as $valor) {
        // Digits are allowed: they are part of proper names like "Lote 111".
        expect($valor)->toMatch('/^[a-z0-9_]+$/')->and(strlen($valor))->toBeLessThanOrEqual(32);
    }
});

test('there are exactly sixty neighborhoods, without duplicated identifiers or labels', function () {
    $valores = array_column(Barrio::cases(), 'value');
    $etiquetas = array_map(fn (Barrio $barrio) => $barrio->etiqueta(), Barrio::cases());

    expect(Barrio::cases())->toHaveCount(60)
        ->and(array_unique($valores))->toHaveCount(60)
        ->and(array_unique($etiquetas))->toHaveCount(60);
});

test('neighborhoods are grouped in five zones, in order', function () {
    expect(array_map('count', Barrio::porZona()))->toBe([
        'Centro y Alrededores' => 9,
        'Zona Norte y Circuito 5' => 12,
        'Zona Oeste y Sudoeste' => 15,
        'Zona Sur y Expansión' => 15,
        'Otros Sectores' => 9,
    ]);
});

test('some neighborhoods keep their exact identifier, label and zone', function (Barrio $barrio, string $valor, string $etiqueta, string $zona) {
    expect($barrio->value)->toBe($valor)
        ->and($barrio->etiqueta())->toBe($etiqueta)
        ->and($barrio->zona())->toBe($zona);
})->with([
    [Barrio::Centro, 'centro', 'Centro', 'Centro y Alrededores'],
    [Barrio::OchoDeOctubre, 'ocho_de_octubre', '8 de Octubre', 'Zona Norte y Circuito 5'],
    [Barrio::LasOrquideas, 'las_orquideas', 'Las Orquídeas', 'Zona Norte y Circuito 5'],
    [Barrio::DiecisieteDeOctubre, 'diecisiete_de_octubre', '17 de Octubre', 'Zona Oeste y Sudoeste'],
    [Barrio::BernardinoRivadaviaLote4, 'bernardino_rivadavia_lote_4', 'Bernardino Rivadavia (Lote 4)', 'Zona Sur y Expansión'],
    [Barrio::Lote111, 'lote_111', 'Lote 111', 'Zona Sur y Expansión'],
    [Barrio::UrbanizacionEspana, 'urbanizacion_espana', 'Urbanización España', 'Zona Sur y Expansión'],
    [Barrio::Malvinas, 'malvinas', 'Malvinas', 'Otros Sectores'],
]);

test('the neighborhoods removed with the old list no longer exist', function (string $valor) {
    expect(Barrio::tryFrom($valor))->toBeNull();
})->with(['san_cayetano', 'primero_de_mayo', 'divino_nino']);
