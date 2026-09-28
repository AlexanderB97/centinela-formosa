<?php

use App\Models\Noticia;
use App\Models\UsuarioStaff;
use App\Services\ImagenDeNoticia;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    Storage::fake(ImagenDeNoticia::DISCO);
});

/**
 * A real JPEG made with GD, with an EXIF-like APP1 segment carrying fake GPS text right after
 * the SOI marker, and a PHP payload appended after the end of the image.
 */
function jpegConExifYCodigoOculto(): string
{
    $imagen = imagecreatetruecolor(40, 30);
    imagefill($imagen, 0, 0, imagecolorallocate($imagen, 22, 163, 74));
    ob_start();
    imagejpeg($imagen);
    $jpeg = (string) ob_get_clean();

    $exif = "Exif\0\0GPS-SECRETO -26.1775,-58.1781";
    $app1 = "\xFF\xE1".pack('n', strlen($exif) + 2).$exif;

    return substr($jpeg, 0, 2).$app1.substr($jpeg, 2).'<?php system($_GET["c"]); ?>';
}

function guardarNoticiaConImagen(UploadedFile $archivo, ?Noticia $noticia = null)
{
    test()->actingAs(UsuarioStaff::factory()->create(), 'staff');

    $componente = Livewire::test('pages::staff.editar-noticia', $noticia ? ['noticia' => $noticia] : []);

    if ($noticia === null) {
        $componente->set('titulo', 'Una noticia')->set('cuerpo', 'Texto de la noticia.');
    }

    return $componente->set('imagen', $archivo)->call('guardar');
}

test('a valid image is re-encoded to WebP under a server-generated name', function (string $nombre) {
    guardarNoticiaConImagen(UploadedFile::fake()->image($nombre, 800, 600))->assertHasNoErrors();

    $ruta = Noticia::sole()->imagen_ruta;
    $bytes = Storage::disk(ImagenDeNoticia::DISCO)->get($ruta);

    expect($ruta)->toMatch('~^noticias/[A-Za-z0-9]{40}\.webp$~')
        ->and($ruta)->not->toContain(pathinfo($nombre, PATHINFO_FILENAME))
        ->and(substr($bytes, 0, 4))->toBe('RIFF')
        ->and(substr($bytes, 8, 4))->toBe('WEBP');
})->with(['portada-de-juan.jpg', 'portada.png', 'portada.webp']);

test('re-encoding drops EXIF data (GPS) and anything hidden in the file', function () {
    guardarNoticiaConImagen(UploadedFile::fake()->createWithContent('foto-celular.jpg', jpegConExifYCodigoOculto()))
        ->assertHasNoErrors();

    $bytes = Storage::disk(ImagenDeNoticia::DISCO)->get(Noticia::sole()->imagen_ruta);

    expect($bytes)->not->toContain('GPS-SECRETO')
        ->and($bytes)->not->toContain('-26.1775')
        ->and($bytes)->not->toContain('<?php');
});

test('large images are scaled down to the stored width', function () {
    guardarNoticiaConImagen(UploadedFile::fake()->image('grande.jpg', 3000, 1500))->assertHasNoErrors();

    [$ancho, $alto] = getimagesizefromstring(Storage::disk(ImagenDeNoticia::DISCO)->get(Noticia::sole()->imagen_ruta));

    expect($ancho)->toBe(ImagenDeNoticia::ANCHO_GUARDADO)->and($alto)->toBe(800);
});

test('if GD cannot scale a large image, nothing is stored and a clear message is shown', function () {
    // imagescale() only fails on rare conditions (e.g. out of memory), so the service is replaced by one where it fails.
    app()->instance(ImagenDeNoticia::class, new class extends ImagenDeNoticia
    {
        protected function escalar(GdImage $imagen, int $ancho): GdImage|false
        {
            return false;
        }
    });

    guardarNoticiaConImagen(UploadedFile::fake()->image('grande.jpg', ImagenDeNoticia::ANCHO_GUARDADO + 1, 100))
        ->assertHasErrors('imagen')
        ->assertSee('No pudimos procesar la imagen. Probá con otra.');

    expect(Noticia::count())->toBe(0)
        ->and(Storage::disk(ImagenDeNoticia::DISCO)->allFiles(ImagenDeNoticia::CARPETA))->toBe([]);
});

test('images that fit the stored width are not scaled at all', function () {
    app()->instance(ImagenDeNoticia::class, new class extends ImagenDeNoticia
    {
        protected function escalar(GdImage $imagen, int $ancho): GdImage|false
        {
            throw new LogicException('No debería escalar una imagen que ya entra.');
        }
    });

    guardarNoticiaConImagen(UploadedFile::fake()->image('justa.jpg', ImagenDeNoticia::ANCHO_GUARDADO, 100))->assertHasNoErrors();

    expect(Noticia::sole()->imagen_ruta)->not->toBeNull();
});

test('only real JPG, PNG or WebP images are accepted', function (Closure $archivo) {
    guardarNoticiaConImagen($archivo())
        ->assertHasErrors('imagen')
        ->assertSee('La imagen tiene que ser JPG, PNG o WebP.');

    expect(Noticia::count())->toBe(0)
        ->and(Storage::disk(ImagenDeNoticia::DISCO)->allFiles(ImagenDeNoticia::CARPETA))->toBe([]);
})->with([
    'HTML disguised as .jpg' => [fn () => UploadedFile::fake()->createWithContent('portada.jpg', '<html><script>alert(1)</script></html>')],
    'PHP disguised as .png' => [fn () => UploadedFile::fake()->createWithContent('portada.png', '<?php echo "hola"; ?>')],
    'SVG' => [fn () => UploadedFile::fake()->createWithContent('portada.svg', '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>')],
    'GIF' => [fn () => UploadedFile::fake()->image('portada.gif', 10, 10)],
    'PDF' => [fn () => UploadedFile::fake()->create('portada.pdf', 10, 'application/pdf')],
]);

test('images over 2 MB are rejected', function () {
    guardarNoticiaConImagen(UploadedFile::fake()->image('pesada.jpg', 100, 100)->size(ImagenDeNoticia::MAXIMO_KB + 1))
        ->assertHasErrors('imagen')
        ->assertSee('La imagen no puede pesar más de 2 MB.');

    expect(Noticia::count())->toBe(0);
});

test('images over the maximum side are rejected before decoding them', function () {
    guardarNoticiaConImagen(UploadedFile::fake()->image('enorme.png', ImagenDeNoticia::MAXIMO_LADO + 1, 10))
        ->assertHasErrors(['imagen' => 'dimensions']);

    expect(Noticia::count())->toBe(0);
});

test('an invalid image is flagged as soon as it finishes uploading', function () {
    test()->actingAs(UsuarioStaff::factory()->create(), 'staff');

    Livewire::test('pages::staff.editar-noticia')
        ->set('imagen', UploadedFile::fake()->createWithContent('portada.jpg', 'no soy una imagen'))
        ->assertHasErrors('imagen')
        ->assertSeeHtml('data-test="imagen-error"');
});

test('the browser checks type and size before uploading', function () {
    test()->actingAs(UsuarioStaff::factory()->create(), 'staff');

    $html = Livewire::test('pages::staff.editar-noticia')->html();

    preg_match('~<input[^>]*id="imagen-noticia"[^>]*>~s', $html, $input);

    expect($input[0])->not->toContain('wire:model')
        ->and($html)->toContain('archivo.size > '.(ImagenDeNoticia::MAXIMO_KB * 1024))
        ->and($html)->toContain('$wire.upload(');
});

test('replacing the image deletes the previous file', function () {
    $disco = Storage::disk(ImagenDeNoticia::DISCO);
    $disco->put('noticias/anterior.webp', 'vieja');
    $noticia = Noticia::factory()->create(['imagen_ruta' => 'noticias/anterior.webp']);

    guardarNoticiaConImagen(UploadedFile::fake()->image('nueva.jpg', 50, 50), $noticia)->assertHasNoErrors();

    expect($disco->exists('noticias/anterior.webp'))->toBeFalse()
        ->and($disco->exists($noticia->refresh()->imagen_ruta))->toBeTrue();
});

test('removing the image deletes the file and clears the column', function () {
    $disco = Storage::disk(ImagenDeNoticia::DISCO);
    $disco->put('noticias/portada.webp', 'imagen');
    $noticia = Noticia::factory()->create(['imagen_ruta' => 'noticias/portada.webp']);
    test()->actingAs(UsuarioStaff::factory()->create(), 'staff');

    Livewire::test('pages::staff.editar-noticia', ['noticia' => $noticia])
        ->assertSeeHtml('data-test="imagen-actual"')
        ->call('$set', 'quitarImagen', true)
        ->call('guardar')
        ->assertHasNoErrors();

    expect($noticia->refresh()->imagen_ruta)->toBeNull()
        ->and($disco->exists('noticias/portada.webp'))->toBeFalse();
});

test('a published image is served as WebP with safe headers', function () {
    Storage::disk(ImagenDeNoticia::DISCO)->put('noticias/portada.webp', 'RIFF----WEBP');
    $noticia = Noticia::factory()->publicada()->create(['imagen_ruta' => 'noticias/portada.webp']);

    $respuesta = $this->get($noticia->urlImagen());

    $respuesta->assertOk()
        ->assertHeader('Content-Type', 'image/webp')
        ->assertHeader('X-Content-Type-Options', 'nosniff');

    expect($respuesta->headers->get('Content-Security-Policy'))->toContain("default-src 'none'")
        ->and($respuesta->headers->get('Cache-Control'))->toContain('public');
});

test('a draft image is 404 for visitors but visible to staff', function () {
    Storage::disk(ImagenDeNoticia::DISCO)->put('noticias/borrador.webp', 'RIFF----WEBP');
    $noticia = Noticia::factory()->create(['imagen_ruta' => 'noticias/borrador.webp']);

    $this->get(route('noticias.imagen', $noticia))->assertNotFound();

    $this->actingAs(UsuarioStaff::factory()->create(), 'staff');

    expect($this->get(route('noticias.imagen', $noticia))->assertOk()->headers->get('Cache-Control'))
        ->toContain('no-store');
});

test('a news without image, or with a missing file, is 404', function () {
    $sinImagen = Noticia::factory()->publicada()->create();
    $archivoPerdido = Noticia::factory()->publicada()->create(['imagen_ruta' => 'noticias/no-existe.webp']);

    $this->get(route('noticias.imagen', $sinImagen))->assertNotFound();
    $this->get(route('noticias.imagen', $archivoPerdido))->assertNotFound();
});
