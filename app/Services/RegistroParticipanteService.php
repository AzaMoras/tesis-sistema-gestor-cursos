<?php

namespace App\Services;

use App\Models\Participante;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RegistroParticipanteService
{
    public function registrar(string $nombre, string $correo, string $password): User
    {
        try {
            return DB::transaction(function () use ($nombre, $correo, $password): User {
                $user = new User(['name' => $nombre, 'email' => $correo, 'password' => $password]);
                // Activa permite autenticar; verified controla el acceso académico.
                $user->estado_cuenta = 'activa';
                $user->email_verified_at = null;
                $user->save();

                $perfil = new Participante;
                $perfil->fecha_alta = today();
                $user->participante()->save($perfil);

                // usuario_rol exige institución: el alta pública no concede pertenencia.
                return $user;
            });
        } catch (UniqueConstraintViolationException $exception) {
            // La restricción única sigue protegiendo frente a registros simultáneos.
            if (DB::table('users')->where('email', $correo)->exists()) {
                throw ValidationException::withMessages([
                    'email' => 'No fue posible registrar ese correo. Intenta iniciar sesión o utiliza otro correo.',
                ]);
            }

            throw $exception;
        }
    }
}
