<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\EmailVerificationRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;

class VerificacionCorreoController extends Controller
{
    public function notice(Request $request): View|RedirectResponse
    {
        return $request->user()->hasVerifiedEmail()
            ? redirect()->route('cuenta')
            : view('auth.verify-email');
    }

    public function verify(EmailVerificationRequest $request): RedirectResponse
    {
        $request->fulfill();

        return redirect()->route('cuenta')->with('status', 'Tu correo electrónico ha sido verificado.');
    }

    public function send(Request $request): RedirectResponse
    {
        if ($request->user()->hasVerifiedEmail()) {
            return redirect()->route('cuenta');
        }

        try {
            $request->user()->sendEmailVerificationNotification();
            $mensaje = 'Enviamos un nuevo enlace de verificación a tu correo.';
        } catch (TransportExceptionInterface) {
            $mensaje = 'No pudimos enviar el correo. Intenta reenviarlo más tarde.';
        }

        return redirect()->route('verification.notice')->with('status', $mensaje);
    }
}
