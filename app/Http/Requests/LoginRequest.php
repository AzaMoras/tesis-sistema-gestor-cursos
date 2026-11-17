<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class LoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->email)) {
            $this->merge(['email' => mb_strtolower(trim($this->email))]);
        }
    }

    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email', 'max:255'],
            'password' => ['required', 'string', 'max:1024'],
        ];
    }

    public function messages(): array
    {
        return [
            'email.required' => 'Escribe tu correo electrónico.',
            'email.string' => 'Escribe un correo electrónico válido.',
            'email.email' => 'Escribe un correo electrónico válido.',
            'email.max' => 'El correo no debe superar los 255 caracteres.',
            'password.required' => 'Escribe tu contraseña.',
            'password.string' => 'Escribe una contraseña válida.',
            'password.max' => 'La contraseña no debe superar los 1024 caracteres.',
        ];
    }
}
