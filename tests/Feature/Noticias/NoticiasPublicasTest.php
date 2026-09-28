<?php

use App\Models\Noticia;
use App\Services\PublicarNoticia;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

test('/noticias shows only published news, newest first', function () {
    Noticia::factory()->publicada('2026-09-10 12:00:00')->create(['titulo' => 'Noticia vieja']);
    Noticia::factory()->publicada('2026-09-20 12:00:00')->create(['titulo' => 'Noticia nueva']);
    Noticia::factory()->create(['titulo' => 'Borrador secreto']);

    $this->get(route('noticias'))
        ->assertOk()
        ->assertSeeInOrder(['Noticia nueva', 'Noticia vieja'])
        ->assertDontSee('Borrador secreto')
        ->assertSee('20/09/2026');
});

test('/noticias paginates ten per page', function () {
    foreach (range(1, 12) as $numero) {
        Noticia::factory()->publicada(sprintf('2026-09-%02d 12:00:00', $numero))->create(['titulo' => "Noticia número {$numero}."]);
    }

    Livewire::test('pages::noticias')
        ->assertSee('Noticia número 12.')
        ->assertSee('Noticia número 3.')
        ->assertDontSee('Noticia número 2.')
        ->call('gotoPage', 2)
        ->assertSee('Noticia número 2.')
        ->assertSee('Noticia número 1.')
        ->assertDontSee('Noticia número 12.');
});

test('/noticias has an empty state', function () {
    Noticia::factory()->create();

    $this->get(route('noticias'))->assertOk()->assertSee('Todavía no hay noticias');
});

test('the detail page shows a published news, and drafts or missing ids are 404', function () {
    $publicada = Noticia::factory()->publicada()->create(['titulo' => 'Nueva estafa por WhatsApp']);
    $borrador = Noticia::factory()->create();

    $this->get(route('noticias.show', $publicada))
        ->assertOk()
        ->assertSee('Nueva estafa por WhatsApp')
        ->assertSee('Nueva estafa por WhatsApp - Centinela Formosa');

    $this->get(route('noticias.show', $borrador))->assertNotFound();
    $this->get('/noticias/999')->assertNotFound();
});

test('the body is plain text: HTML and scripts are shown escaped, never run', function () {
    $noticia = Noticia::factory()->publicada()->create([
        'titulo' => '<img src=x onerror=alert(1)>',
        'cuerpo' => "<script>alert('xss')</script>\n\n<a href=\"https://estafa.test\">Tocá acá</a>",
    ]);

    $this->get(route('noticias.show', $noticia))
        ->assertOk()
        ->assertDontSee("<script>alert('xss')</script>", false)
        ->assertDontSee('<img src=x', false)
        ->assertDontSee('<a href="https://estafa.test"', false)
        ->assertSee('&lt;script&gt;', false);

    $this->get(route('noticias'))->assertDontSee('<img src=x', false)->assertDontSee('<script>alert', false);
});

test('blank lines split paragraphs and single line breaks stay inside a paragraph', function () {
    $noticia = Noticia::factory()->publicada()->create(['cuerpo' => "Primer párrafo\ncon dos líneas.\r\n\r\n\n\nSegundo párrafo."]);

    expect($noticia->parrafos())->toBe(["Primer párrafo\ncon dos líneas.", 'Segundo párrafo.']);

    $this->get(route('noticias.show', $noticia))
        ->assertSeeInOrder(['<p class="whitespace-pre-line', 'Primer párrafo', '<p class="whitespace-pre-line', 'Segundo párrafo.'], false);
});

test('unpublishing removes the news from every public page', function () {
    $noticia = Noticia::factory()->publicada()->create(['titulo' => 'Aviso que se retira']);

    $this->get(route('noticias'))->assertSee('Aviso que se retira');
    $this->get(route('home'))->assertSee('Aviso que se retira');

    app(PublicarNoticia::class)->despublicar($noticia);

    $this->get(route('noticias'))->assertDontSee('Aviso que se retira');
    $this->get(route('home'))->assertDontSee('Aviso que se retira');
    $this->get(route('noticias.show', $noticia))->assertNotFound();
});

test('the home page features the latest three published news', function () {
    foreach (range(1, 4) as $numero) {
        Noticia::factory()->publicada("2026-09-0{$numero} 12:00:00")->create(['titulo' => "Portada {$numero}."]);
    }
    Noticia::factory()->create(['titulo' => 'Borrador de portada']);

    $this->get(route('home'))
        ->assertOk()
        ->assertSee('Últimas noticias')
        ->assertSeeInOrder(['Analizador de riesgo', 'Portada 4.', 'Portada 3.', 'Portada 2.'])
        ->assertDontSee('Portada 1.')
        ->assertDontSee('Borrador de portada');
});

test('the home page hides the section when nothing is published', function () {
    Noticia::factory()->create();

    $this->get(route('home'))->assertOk()->assertDontSee('Últimas noticias');
});

test('analyzing does not query the news again', function () {
    Noticia::factory()->publicada()->create();

    $analizador = Livewire::test('pages::analizador');

    $consultas = 0;
    DB::listen(function ($consulta) use (&$consultas) {
        $consultas += str_contains($consulta->sql, 'noticias') ? 1 : 0;
    });

    $analizador->call('seleccionarTipo', 'link')->call('seleccionarTipo', 'texto');

    expect($consultas)->toBe(0);
});

test('the public navigation links to the news', function () {
    $this->get(route('impacto'))->assertSee(route('noticias'));
    $this->get(route('noticias'))->assertSeeHtml('aria-current="page"');
});
