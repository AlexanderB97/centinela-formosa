<?php

namespace App\Models;

use App\Enums\EstadoNoticia;
use Database\Factories\NoticiaFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * News written by staff. estado and publicada_en only change through PublicarNoticia.
 *
 * The body is plain text: views render it escaped, paragraph by paragraph (see parrafos()).
 * The author is shown only in the staff panel, never on public pages.
 *
 * @property int $id
 * @property string $titulo
 * @property string $cuerpo
 * @property string|null $imagen_ruta
 * @property EstadoNoticia $estado
 * @property Carbon|null $publicada_en
 * @property int|null $usuario_staff_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['titulo', 'cuerpo', 'imagen_ruta', 'usuario_staff_id'])]
class Noticia extends Model
{
    /** @use HasFactory<NoticiaFactory> */
    use HasFactory;

    public const ZONA_HORARIA = 'America/Argentina/Cordoba';

    protected $table = 'noticias';

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'estado' => 'borrador',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'estado' => EstadoNoticia::class,
            'publicada_en' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<UsuarioStaff, $this>
     */
    public function autor(): BelongsTo
    {
        return $this->belongsTo(UsuarioStaff::class, 'usuario_staff_id');
    }

    /**
     * Published news only, newest first. Every public query must start here.
     *
     * @param  Builder<self>  $consulta
     */
    public function scopePublicadas(Builder $consulta): void
    {
        $consulta->where('estado', EstadoNoticia::Publicada->value)
            ->latest('publicada_en')
            ->latest('id');
    }

    public function estaPublicada(): bool
    {
        return $this->estado === EstadoNoticia::Publicada;
    }

    /**
     * Paragraphs of the body, split on blank lines. Single line breaks stay inside the paragraph.
     *
     * @return list<string>
     */
    public function parrafos(): array
    {
        $parrafos = preg_split('/\R\s*\R/u', trim(str_replace("\r\n", "\n", $this->cuerpo))) ?: [];

        return array_values(array_filter(array_map('trim', $parrafos), fn (string $parrafo) => $parrafo !== ''));
    }

    /**
     * URL of the cover image. The "v" part changes when the image is replaced, so caches never
     * keep serving the previous one.
     */
    public function urlImagen(): ?string
    {
        if ($this->imagen_ruta === null) {
            return null;
        }

        return route('noticias.imagen', ['noticia' => $this->id, 'v' => substr(basename($this->imagen_ruta), 0, 8)]);
    }

    /**
     * Publication date as shown to people in Formosa (the app itself runs in UTC).
     */
    public function fechaPublicacion(): ?string
    {
        return $this->publicada_en?->copy()->setTimezone(self::ZONA_HORARIA)->format('d/m/Y');
    }

    public function extracto(int $largo = 220): string
    {
        return Str::limit(Str::squish($this->cuerpo), $largo);
    }
}
