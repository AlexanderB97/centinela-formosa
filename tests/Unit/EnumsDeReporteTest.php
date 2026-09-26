<?php

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

test('stored values are plain identifiers without accents or spaces', function () {
    $valores = [...array_column(Departamento::cases(), 'value'), ...array_column(MedioRecepcion::cases(), 'value')];

    foreach ($valores as $valor) {
        expect($valor)->toMatch('/^[a-z_]+$/')->and(strlen($valor))->toBeLessThanOrEqual(32);
    }
});
