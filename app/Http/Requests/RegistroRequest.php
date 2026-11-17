<?php

namespace App\Http\Requests;

use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rules\Password;

class RegistroRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        foreach (['name', 'email'] as $field) {
            if (is_string($this->input($field))) {
                $value = trim($this->input($field));
                $this->merge([$field => $field === 'email' ? mb_strtolower($value) : $value]);
            }
        }
    }

    public function rules(): array
    {
        return [
            'name' => ['bail', 'required', 'string', 'max:255'],
            'email' => ['bail', 'required', 'string', 'email', 'max:255', function (string $attribute, mixed $value, Closure $fail): void {
                // Incluye bajas lógicas y correos históricos con mayúsculas.
                if (DB::table('users')->whereRaw('LOWER(email) = ?', [$value])->exists()) {
                    $fail('No fue posible registrar ese correo. Intenta iniciar sesión o utiliza otro correo.');
                }
            }],
            'password' => ['bail', 'required', 'string', 'max:72', function (string $attribute, mixed $value, Closure $fail): void {
                if (strlen($value) > 72) {
                    $fail('La contraseña es demasiado larga. Usa menos caracteres.');
                }
            }, 'confirmed', Password::min(12)->mixedCase()->numbers()->symbols()],
            'password_confirmation' => ['required', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Escribe tu nombre.',
            'name.string' => 'Escribe un nombre válido.',
            'name.max' => 'El nombre no debe superar los 255 caracteres.',
            'email.required' => 'Escribe tu correo electrónico.',
            'email.string' => 'Escribe un correo electrónico válido.',
            'email.email' => 'Escribe un correo electrónico válido.',
            'email.max' => 'El correo no debe superar los 255 caracteres.',
            'password.required' => 'Escribe tu contraseña.',
            'password.string' => 'Escribe una contraseña válida.',
            'password.max' => 'La contraseña no debe superar los 72 caracteres.',
            'password.confirmed' => 'La confirmación de contraseña no coincide.',
            'password.min' => 'La contraseña debe tener al menos 12 caracteres.',
            'password.mixed' => 'La contraseña debe incluir mayúsculas y minúsculas.',
            'password.numbers' => 'La contraseña debe incluir un número.',
            'password.symbols' => 'La contraseña debe incluir un símbolo.',
            'password_confirmation.required' => 'Confirma tu contraseña.',
            'password_confirmation.string' => 'Escribe una confirmación válida.',
        ];
    }
}
