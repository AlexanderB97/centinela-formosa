<?php

use App\Enums\NivelRiesgo;
use App\Enums\TipoContenido;
use App\Models\Analisis;
use App\Models\CasoConfirmado;
use App\Services\RankingConsultados;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;

/**
 * @param  list<string>|string  $contenidos  one per analysis, or the same content $veces times
 */
function analizado(TipoContenido $tipo, array|string $contenidos, int $veces = 1, NivelRiesgo $nivel = NivelRiesgo::Riesgo): void
{
    foreach ((array) $contenidos as $contenido) {
        Analisis::factory()->count($veces)->create(['tipo' => $tipo, 'contenido' => $contenido, 'nivel' => $nivel]);
    }
}

function ranking(): array
{
    return app(RankingConsultados::class)->obtener();
}

test('it groups repeated content regardless of casing and spacing', function () {
    analizado(TipoContenido::Texto, ['Ganaste un premio', 'GANASTE   un premio', " ganaste un PREMIO\n"]);

    expect(ranking()['texto'])->toHaveCount(1)
        ->and(ranking()['texto'][0]['veces'])->toBe(3);
});

test('it groups links regardless of scheme, www, casing and trailing slash', function () {
    analizado(TipoContenido::Link, ['https://www.bit.ly/Premio', 'http://bit.ly/premio/', 'bit.ly/premio']);

    expect(ranking()['link'])->toHaveCount(1)
        ->and(ranking()['link'][0]['veces'])->toBe(3);
});

test('it keeps the three types separate', function () {
    analizado(TipoContenido::Link, 'https://premios-formosa.top/reclamar', 3);
    analizado(TipoContenido::Qr, 'https://premios-formosa.top/reclamar', 4);

    $resultado = ranking();

    expect($resultado['texto'])->toBe([])
        ->and($resultado['link'][0]['veces'])->toBe(3)
        ->and($resultado['qr'][0]['veces'])->toBe(4);
});

test('content analyzed fewer than three times is not listed', function () {
    analizado(TipoContenido::Texto, 'Solo dos veces', 2);

    expect(ranking()['texto'])->toBe([]);
});

test('content that was only ever seguro is not listed', function () {
    analizado(TipoContenido::Texto, 'Nos vemos mañana', 5, NivelRiesgo::Seguro);

    expect(ranking()['texto'])->toBe([]);
});

test('the level shown is the highest one ever detected', function () {
    analizado(TipoContenido::Texto, 'Mayormente seguro', 4, NivelRiesgo::Seguro);
    analizado(TipoContenido::Texto, 'Mayormente seguro', 1, NivelRiesgo::Dudoso);
    analizado(TipoContenido::Texto, 'Una vez en riesgo', 3, NivelRiesgo::Dudoso);
    analizado(TipoContenido::Texto, 'Una vez en riesgo', 1, NivelRiesgo::Riesgo);

    expect(collect(ranking()['texto'])->pluck('nivel', 'contenido')->all())->toBe([
        'Mayormente seguro' => 'dudoso',
        'Una vez en riesgo' => 'riesgo',
    ]);
});

test('it returns the top five by count, most recent first on ties', function () {
    foreach ([3, 8, 5, 4, 7, 6] as $veces) {
        analizado(TipoContenido::Texto, "Mensaje repetido {$veces} veces", $veces);
    }
    analizado(TipoContenido::Texto, 'Empate más reciente', 7);

    expect(collect(ranking()['texto'])->pluck('veces')->all())->toBe([8, 7, 7, 6, 5])
        ->and(ranking()['texto'][1]['contenido'])->toBe('Empate más reciente');
});

test('it flags content that matches a confirmed case', function () {
    analizado(TipoContenido::Texto, ['Hola, soy tu nieto, cambié de número', 'Ganaste un premio'], 3);
    CasoConfirmado::factory()->create(['contenido' => 'HOLA, soy tu nieto,  cambié de número']);

    expect(collect(ranking()['texto'])->pluck('confirmado', 'contenido')->all())->toBe([
        'Ganaste un premio' => false,
        'Hola, soy tu nieto, cambié de número' => true,
    ]);
});

test('the content shown is masked', function () {
    analizado(TipoContenido::Texto, 'Tu tarjeta 4509 9535 6623 3704 fue bloqueada, escribí a soporte@banco-falso.com', 3);
    analizado(TipoContenido::Link, 'https://reset-clave.top/r?token=secreto123', 3);

    $resultado = ranking();

    expect($resultado['texto'][0]['contenido'])->toBe('Tu tarjeta [número] fue bloqueada, escribí a [email]')
        ->and($resultado['link'][0]['contenido'])->toBe('https://reset-clave.top/r');
});

test('with no data every category is empty', function () {
    expect(ranking())->toBe(['texto' => [], 'link' => [], 'qr' => []]);
});

test('it runs a fixed number of aggregate queries, whatever the number of rows', function () {
    analizado(TipoContenido::Texto, ['Uno', 'Dos', 'Tres', 'Cuatro', 'Cinco', 'Seis'], 3);
    analizado(TipoContenido::Link, 'https://premios-formosa.top/reclamar', 4);
    analizado(TipoContenido::Texto, 'Nunca debe leerse entero', 2);

    $consultas = [];
    DB::listen(function (QueryExecuted $consulta) use (&$consultas) {
        $consultas[] = strtolower($consulta->sql);
    });

    ranking();

    // One GROUP BY per type, then the winners' content and the confirmed cases.
    expect($consultas)->toHaveCount(5);

    foreach (array_slice($consultas, 0, 3) as $sql) {
        expect($sql)->toContain('group by')->toContain('count(*)')->not->toContain('contenido');
    }

    foreach ($consultas as $sql) {
        expect($sql)->not->toMatch('/select\s+\*/');
    }
});
