<?php

use App\Enums\EstadoReporte;
use App\Http\Requests\ReportarRequest;
use App\Models\Analisis;
use App\Models\Reporte;
use App\Services\ReportarAnalisis;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

test('guests can report an analysis with a comment', function () {
    $analisis = Analisis::factory()->create();

    $this->postJson(route('reportar.store'), ['analisis_id' => $analisis->id, 'comentario' => 'Me llegó por WhatsApp.'])
        ->assertCreated()
        ->assertExactJson(['mensaje' => ReportarAnalisis::MENSAJE_EXITO]);

    $reporte = Reporte::sole();

    expect($reporte->analisis_id)->toBe($analisis->id)
        ->and($reporte->comentario)->toBe('Me llegó por WhatsApp.')
        ->and($reporte->estado)->toBe(EstadoReporte::Pendiente)
        ->and($reporte->analisis->is($analisis))->toBeTrue();
});

test('the comment is optional and a blank one is stored as null', function (array $extra) {
    $analisis = Analisis::factory()->create();

    $this->postJson(route('reportar.store'), ['analisis_id' => $analisis->id, ...$extra])
        ->assertCreated()
        ->assertExactJson(['mensaje' => ReportarAnalisis::MENSAJE_EXITO]);

    expect(Reporte::sole()->comentario)->toBeNull();
})->with([
    'without comment' => [[]],
    'null comment' => [['comentario' => null]],
    'blank comment' => [['comentario' => '   ']],
]);

test('it works without csrf token or accept header, like /analizar', function () {
    $analisis = Analisis::factory()->create();

    $this->post('/reportar', ['analisis_id' => $analisis->id])->assertCreated();
    $this->post('/reportar', ['analisis_id' => 999])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('analisis_id');
});

test('an unknown analisis_id returns 422 with a clear message', function () {
    $this->postJson(route('reportar.store'), ['analisis_id' => 999])
        ->assertUnprocessable()
        ->assertJsonPath('message', ReportarRequest::MENSAJE_INEXISTENTE)
        ->assertJsonValidationErrors(['analisis_id' => ReportarRequest::MENSAJE_INEXISTENTE]);

    expect(Reporte::count())->toBe(0);
});

test('reporting the same analysis twice returns 422 and does not create a second report', function () {
    $analisis = Analisis::factory()->create();

    $this->postJson(route('reportar.store'), ['analisis_id' => $analisis->id])->assertCreated();

    $this->postJson(route('reportar.store'), ['analisis_id' => $analisis->id, 'comentario' => 'Otra vez'])
        ->assertUnprocessable()
        ->assertJsonPath('message', 'Ya reportaste este análisis, gracias.')
        ->assertJsonValidationErrors(['analisis_id' => 'Ya reportaste este análisis, gracias.']);

    expect(Reporte::count())->toBe(1)
        ->and(Reporte::sole()->comentario)->toBeNull();
});

test('a concurrent duplicate caught by the unique index is also a friendly 422', function () {
    $analisis = Analisis::factory()->create();
    $servicio = app(ReportarAnalisis::class);

    $servicio->registrar($analisis->id, null);

    expect(fn () => $servicio->registrar($analisis->id, 'segundo'))
        ->toThrow(ValidationException::class, ReportarRequest::MENSAJE_YA_REPORTADO);
    expect(Reporte::count())->toBe(1);
});

test('it validates the request', function (array $datos, string $campo) {
    $analisis = Analisis::factory()->create();

    $this->postJson(route('reportar.store'), array_merge(['analisis_id' => $analisis->id], $datos))
        ->assertUnprocessable()
        ->assertJsonValidationErrors($campo);

    expect(Reporte::count())->toBe(0);
})->with([
    'missing analisis_id' => [['analisis_id' => null], 'analisis_id'],
    'analisis_id not an integer' => [['analisis_id' => 'abc'], 'analisis_id'],
    'comment too long' => [['comentario' => str_repeat('a', 501)], 'comentario'],
    'comment not a string' => [['comentario' => ['a']], 'comentario'],
]);

test('a 500 character comment is accepted', function () {
    $analisis = Analisis::factory()->create();

    $this->postJson(route('reportar.store'), ['analisis_id' => $analisis->id, 'comentario' => str_repeat('a', 500)])
        ->assertCreated();
});

test('reports never store or return anything about the visitor', function () {
    expect(Schema::getColumnListing('reportes'))
        ->toEqualCanonicalizing(['id', 'analisis_id', 'comentario', 'estado', 'created_at', 'updated_at']);

    $analisis = Analisis::factory()->create();

    $respuesta = $this->withHeaders(['User-Agent' => 'Navegador-De-Prueba'])
        ->postJson(route('reportar.store'), ['analisis_id' => $analisis->id, 'comentario' => 'hola'])
        ->assertCreated();

    expect(array_keys($respuesta->json()))->toBe(['mensaje'])
        ->and(json_encode(Reporte::sole()->getAttributes()))
        ->not->toContain('127.0.0.1')
        ->not->toContain('Navegador-De-Prueba');
});

test('deleting an analysis deletes its report', function () {
    $reporte = Reporte::factory()->create();

    $reporte->analisis->delete();

    expect(Reporte::count())->toBe(0);
});

test('the report api is rate limited per ip', function () {
    $analisis = Analisis::factory()->count(11)->create();

    foreach ($analisis->take(10) as $uno) {
        $this->postJson(route('reportar.store'), ['analisis_id' => $uno->id])->assertCreated();
    }

    $this->postJson(route('reportar.store'), ['analisis_id' => $analisis->last()->id])->assertTooManyRequests();
});
