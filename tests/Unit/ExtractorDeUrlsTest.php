<?php

use App\Services\Analisis\ExtractorDeUrls;

test('it extracts links with and without scheme', function () {
    expect(ExtractorDeUrls::extraer('Entrá a https://ejemplo.com/a?b=1, o a bit.ly/xyz. También www.sitio.org!'))
        ->toBe(['https://ejemplo.com/a?b=1', 'bit.ly/xyz', 'www.sitio.org']);
});

test('it ignores things that only look like domains', function () {
    expect(ExtractorDeUrls::extraer('Saludos Sr.Pérez, el precio es 1.500.000 y mi mail es juan@correo.com'))->toBe([]);
});

test('it detects single urls and hosts', function () {
    expect(ExtractorDeUrls::esUrlUnica(' https://ejemplo.com/x '))->toBeTrue()
        ->and(ExtractorDeUrls::esUrlUnica('mirá https://ejemplo.com/x'))->toBeFalse()
        ->and(ExtractorDeUrls::host('WWW.Ejemplo.com/x'))->toBe('www.ejemplo.com')
        ->and(ExtractorDeUrls::conEsquema('bit.ly/x'))->toBe('http://bit.ly/x');
});
