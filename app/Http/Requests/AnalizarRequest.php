<?php

namespace App\Http\Requests;

use App\Enums\TipoContenido;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AnalizarRequest extends FormRequest
{
    public const LARGO_MAXIMO = 5000;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<int, ValidationRule|string>>
     */
    public function rules(): array
    {
        return self::reglas($this->input('tipo'));
    }

    /**
     * Get the custom validation messages.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return self::mensajes($this->input('tipo'));
    }

    /**
     * Rules shared with the Livewire analyzer, so both consumers validate the same way.
     *
     * @return array<string, array<int, ValidationRule|string>>
     */
    public static function reglas(mixed $tipo): array
    {
        return [
            'tipo' => ['required', Rule::enum(TipoContenido::class)],
            'contenido' => array_filter([
                'required',
                'string',
                'max:'.self::LARGO_MAXIMO,
                $tipo === TipoContenido::Link->value ? 'url' : null,
            ]),
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function mensajes(mixed $tipo): array
    {
        return [
            'tipo.required' => 'Elegí qué querés analizar.',
            'tipo.enum' => 'El tipo de contenido no es válido.',
            'contenido.required' => match ($tipo) {
                TipoContenido::Link->value => 'Pegá el link que querés analizar.',
                TipoContenido::Qr->value => 'Primero subí una foto con un código QR.',
                default => 'Pegá el mensaje que querés analizar.',
            },
            'contenido.string' => 'El contenido a analizar debe ser texto.',
            'contenido.max' => 'El contenido no puede superar los '.self::LARGO_MAXIMO.' caracteres.',
            'contenido.url' => 'Ingresá un link completo, por ejemplo https://ejemplo.com.',
        ];
    }
}
