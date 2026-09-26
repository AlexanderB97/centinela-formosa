<?php

namespace App\Http\Requests;

use App\Enums\Departamento;
use App\Enums\MedioRecepcion;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReportarRequest extends FormRequest
{
    public const LARGO_MAXIMO_COMENTARIO = 500;

    public const MENSAJE_INEXISTENTE = 'No encontramos este análisis. Probá analizarlo de nuevo.';

    public const MENSAJE_YA_REPORTADO = 'Ya reportaste este análisis, gracias.';

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
        return self::reglas();
    }

    /**
     * Get the custom validation messages.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return self::mensajes();
    }

    /**
     * Rules shared with the Livewire analyzer, so both consumers validate the same way.
     *
     * @return array<string, array<int, ValidationRule|string>>
     */
    public static function reglas(): array
    {
        return [
            'analisis_id' => ['required', 'integer', 'exists:analisis,id', 'unique:reportes,analisis_id'],
            'comentario' => ['nullable', 'string', 'max:'.self::LARGO_MAXIMO_COMENTARIO],
            'departamento' => ['nullable', Rule::enum(Departamento::class)],
            'medio' => ['nullable', Rule::enum(MedioRecepcion::class)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function mensajes(): array
    {
        return [
            'analisis_id.required' => 'Falta indicar qué análisis querés reportar.',
            'analisis_id.integer' => 'El análisis a reportar no es válido.',
            'analisis_id.exists' => self::MENSAJE_INEXISTENTE,
            'analisis_id.unique' => self::MENSAJE_YA_REPORTADO,
            'comentario.string' => 'El comentario debe ser texto.',
            'comentario.max' => 'El comentario no puede superar los '.self::LARGO_MAXIMO_COMENTARIO.' caracteres.',
            'departamento.enum' => 'El departamento seleccionado no es válido.',
            'medio.enum' => 'El medio seleccionado no es válido.',
        ];
    }
}
