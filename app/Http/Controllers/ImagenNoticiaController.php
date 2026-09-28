<?php

namespace App\Http\Controllers;

use App\Models\Noticia;
use App\Services\ImagenDeNoticia;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ImagenNoticiaController extends Controller
{
    /**
     * Serve the cover image of a news. Drafts return 404 to visitors (only staff can preview them),
     * so a draft image cannot be seen by guessing its URL.
     */
    public function __invoke(Noticia $noticia): StreamedResponse
    {
        abort_unless($noticia->estaPublicada() || auth('staff')->check(), 404);

        $disco = Storage::disk(ImagenDeNoticia::DISCO);

        abort_if($noticia->imagen_ruta === null || ! $disco->exists($noticia->imagen_ruta), 404);

        // Stored images are always WebP re-encoded by the server: the type is fixed, never sniffed.
        return $disco->response($noticia->imagen_ruta, 'portada.webp', [
            'Content-Type' => 'image/webp',
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "default-src 'none'; sandbox",
            'Cache-Control' => $noticia->estaPublicada() ? 'public, max-age=3600' : 'private, no-store',
        ]);
    }
}
