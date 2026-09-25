<?php

namespace App\Http\Requests;

use App\Enums\RolStaff;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class UsuarioStaffRequest extends FormRequest
{
    public const LARGO_MINIMO_PASSWORD = 8;

    /**
     * Determine if the user is authorized to make this request.
     *
     * The admin.staff middleware already restricts the route to admins.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        if (is_string($this->input('email'))) {
            $this->merge(['email' => self::normalizarEmail($this->input('email'))]);
        }
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
     * Emails are stored trimmed and lowercased, so "Ana@X.com" and "ana@x.com" are the same account.
     */
    public static function normalizarEmail(string $email): string
    {
        return Str::lower(trim($email));
    }

    /**
     * Rules shared with the Livewire staff management page, so both consumers validate the same way.
     *
     * @return array<string, array<int, ValidationRule|string>>
     */
    public static function reglas(): array
    {
        return [
            'nombre' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:usuarios_staff,email'],
            'password' => ['required', 'string', 'min:'.self::LARGO_MINIMO_PASSWORD],
            'rol' => ['required', Rule::enum(RolStaff::class)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function mensajes(): array
    {
        return [
            'nombre.required' => 'El nombre es obligatorio.',
            'nombre.max' => 'El nombre no puede superar los 255 caracteres.',
            'email.required' => 'El email es obligatorio.',
            'email.email' => 'El email no tiene un formato válido.',
            'email.max' => 'El email no puede superar los 255 caracteres.',
            'email.unique' => 'Ya existe una cuenta con ese email.',
            'password.required' => 'La contraseña es obligatoria.',
            'password.min' => 'La contraseña debe tener al menos '.self::LARGO_MINIMO_PASSWORD.' caracteres.',
            'rol.required' => 'Seleccioná un rol.',
            'rol.enum' => 'El rol seleccionado no es válido.',
        ];
    }
}
