<?php

namespace App\Services;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\File;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/**
 * Cover images of news. The uploaded file is never stored as is: it is decoded with GD and
 * re-encoded to WebP, which drops EXIF metadata (including GPS) and anything hidden after
 * or inside the image data. It goes to the private "local" disk with a random name and is
 * only served through ImagenNoticiaController.
 */
class ImagenDeNoticia
{
    public const DISCO = 'local';

    public const CARPETA = 'noticias';

    /** Fits the default PHP upload_max_filesize (2M), so no server setting has to change. */
    public const MAXIMO_KB = 2048;

    /** Checked before decoding, so a huge image cannot exhaust memory_limit. */
    public const MAXIMO_LADO = 4000;

    /** Stored images are scaled down to this width: enough for a cover, light to serve. */
    public const ANCHO_GUARDADO = 1600;

    public const CALIDAD_WEBP = 80;

    /**
     * @return array<int, ValidationRule|string|File>
     */
    public static function reglas(): array
    {
        return [
            'nullable',
            // types() checks the extension guessed from the content, not the name the browser sent.
            File::image()->types(['jpg', 'jpeg', 'png', 'webp'])->max(self::MAXIMO_KB),
            'mimetypes:image/jpeg,image/png,image/webp',
            Rule::dimensions()->maxWidth(self::MAXIMO_LADO)->maxHeight(self::MAXIMO_LADO),
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function mensajes(string $campo = 'imagen'): array
    {
        return [
            "{$campo}.image" => 'La imagen tiene que ser JPG, PNG o WebP.',
            "{$campo}.mimes" => 'La imagen tiene que ser JPG, PNG o WebP.',
            "{$campo}.mimetypes" => 'La imagen tiene que ser JPG, PNG o WebP.',
            "{$campo}.max" => 'La imagen no puede pesar más de 2 MB.',
            "{$campo}.dimensions" => 'La imagen no puede medir más de '.self::MAXIMO_LADO.' píxeles de lado.',
            "{$campo}.uploaded" => 'No pudimos subir la imagen. Revisá que pese menos de 2 MB.',
        ];
    }

    /**
     * Re-encode the (already validated) upload to WebP and store it under a random name.
     *
     * @return string the path on the DISCO disk.
     *
     * @throws ValidationException when GD cannot decode the image.
     */
    public function guardar(UploadedFile $archivo): string
    {
        if (! function_exists('imagewebp')) {
            throw new RuntimeException('La extensión GD con soporte WebP es obligatoria para las imágenes de noticias.');
        }

        $imagen = @imagecreatefromstring((string) file_get_contents($archivo->getRealPath()));

        if ($imagen === false) {
            throw ValidationException::withMessages(['imagen' => 'No pudimos leer la imagen. Probá con otro archivo JPG, PNG o WebP.']);
        }

        // Palette PNGs and transparency: work in truecolor and keep the alpha channel.
        imagepalettetotruecolor($imagen);
        imagealphablending($imagen, false);
        imagesavealpha($imagen, true);

        if (imagesx($imagen) > self::ANCHO_GUARDADO) {
            $escalada = imagescale($imagen, self::ANCHO_GUARDADO);
            imagedestroy($imagen);
            $imagen = $escalada;
            imagealphablending($imagen, false);
            imagesavealpha($imagen, true);
        }

        ob_start();
        imagewebp($imagen, null, self::CALIDAD_WEBP);
        $webp = (string) ob_get_clean();
        imagedestroy($imagen);

        $ruta = self::CARPETA.'/'.Str::random(40).'.webp';
        Storage::disk(self::DISCO)->put($ruta, $webp);

        return $ruta;
    }

    public function borrar(?string $ruta): void
    {
        if ($ruta !== null) {
            Storage::disk(self::DISCO)->delete($ruta);
        }
    }
}
