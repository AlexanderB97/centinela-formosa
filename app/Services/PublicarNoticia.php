<?php

namespace App\Services;

use App\Enums\EstadoNoticia;
use App\Models\Noticia;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PublicarNoticia
{
    /**
     * Publish a draft. The first publication sets publicada_en; publishing again after
     * unpublishing keeps the original date, so a correction does not jump to the top.
     *
     * @throws ValidationException when the news is already published.
     */
    public function publicar(Noticia $noticia): Noticia
    {
        return DB::transaction(function () use ($noticia) {
            $this->cambiarEstado($noticia, EstadoNoticia::Borrador, EstadoNoticia::Publicada);

            Noticia::query()->whereKey($noticia->getKey())->whereNull('publicada_en')->update(['publicada_en' => now()]);

            return $noticia->refresh();
        });
    }

    /**
     * Take a published news back to draft: it disappears from every public page. The date is kept.
     *
     * @throws ValidationException when the news is not published.
     */
    public function despublicar(Noticia $noticia): Noticia
    {
        $this->cambiarEstado($noticia, EstadoNoticia::Publicada, EstadoNoticia::Borrador);

        return $noticia->refresh();
    }

    /**
     * Atomic transition: the WHERE on the current state means only one of two concurrent staff
     * members (or a stale page opened before someone else acted) can win the UPDATE.
     */
    private function cambiarEstado(Noticia $noticia, EstadoNoticia $desde, EstadoNoticia $hacia): void
    {
        $actualizadas = Noticia::query()
            ->whereKey($noticia->getKey())
            ->where('estado', $desde->value)
            ->update(['estado' => $hacia->value]);

        if ($actualizadas === 0) {
            $mensaje = $hacia === EstadoNoticia::Publicada
                ? "La noticia #{$noticia->getKey()} ya está publicada."
                : "La noticia #{$noticia->getKey()} ya no está publicada.";

            throw ValidationException::withMessages(['noticia' => $mensaje]);
        }
    }
}
