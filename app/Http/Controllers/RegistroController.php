<?php

namespace App\Http\Controllers;

use App\Http\Requests\RegistroRequest;
use App\Services\RegistroParticipanteService;
use App\Services\Soporte\RetornoInscripcion;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;

class RegistroController extends Controller
{
    public function create(): View
    {
        return view('auth.register');
    }

    public function store(RegistroRequest $request, RegistroParticipanteService $registro, RetornoInscripcion $retorno): RedirectResponse
    {
        $datos = $request->validated();
        $user = $registro->registrar($datos['name'], $datos['email'], $datos['password']);
        Auth::guard('web')->login($user);
        $request->session()->regenerate();
        $request->session()->forget('url.intended');

        // El evento se emite después de persistir ambos registros.
        $verificacionHabilitada = config('auth.verification.enabled');
        try {
            event(new Registered($user));
            $mensaje = $verificacionHabilitada
                ? 'Cuenta creada. Revisa tu correo para verificar tu dirección.'
                : 'Tu cuenta fue creada. Ya puedes acceder con tu correo y contraseña.';
        } catch (TransportExceptionInterface) {
            $mensaje = 'Tu cuenta fue creada, pero no pudimos enviar el correo. Intenta reenviarlo desde esta pantalla.';
        }

        if (! $verificacionHabilitada && ($destino = $retorno->consumir($request))) {
            return redirect($destino)->with('status', $mensaje);
        }

        return redirect()->route($verificacionHabilitada ? 'verification.notice' : 'cuenta')->with('status', $mensaje);
    }
}
