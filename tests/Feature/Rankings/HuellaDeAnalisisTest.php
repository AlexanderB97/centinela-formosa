<?php

use App\Models\Analisis;
use App\Services\Analisis\Huella;
use Illuminate\Support\Facades\DB;

test('an analysis stores the fingerprint of its content', function () {
    $analisis = Analisis::factory()->create(['contenido' => '  Ganaste un PREMIO ']);

    expect($analisis->huella)->toBe(Huella::de('ganaste un premio'));

    $analisis->update(['contenido' => 'Otro mensaje']);

    expect($analisis->refresh()->huella)->toBe(Huella::de('Otro mensaje'));
});

test('the migration backfills the fingerprint of existing analyses', function () {
    // Rows inserted without the model have no fingerprint, like the ones created before the migration.
    foreach (['Hola, soy tu nieto', 'https://www.bit.ly/Premio'] as $contenido) {
        DB::table('analisis')->insert([
            'tipo' => 'texto',
            'contenido' => $contenido,
            'nivel' => 'dudoso',
            'razones' => '[]',
            'explicacion' => 'Explicación.',
            'explicacion_generada_por_ia' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    expect(DB::table('analisis')->whereNull('huella')->count())->toBe(2);

    $migracion = require database_path('migrations/2026_09_26_230727_add_huella_to_analisis_table.php');
    $migracion->rellenarHuellas();

    expect(DB::table('analisis')->pluck('huella', 'contenido')->all())->toBe([
        'Hola, soy tu nieto' => Huella::de('Hola, soy tu nieto'),
        'https://www.bit.ly/Premio' => Huella::de('bit.ly/premio'),
    ]);
});
