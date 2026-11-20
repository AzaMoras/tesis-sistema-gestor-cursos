<?php

namespace App\Http\Controllers;

use App\Http\Requests\LoginRequest;
use App\Services\Soporte\RetornoInscripcion;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class SesionController extends Controller
{
    public function create(): View
    {
        return view('auth.login');
    }

    public function store(LoginRequest $request, RetornoInscripcion $retorno): RedirectResponse
    {
        // Solo el servidor decide el estado admitido; no se aceptan privilegios.
        if (! Auth::guard('web')->attempt($request->safe()->only(['email', 'password']) + ['estado_cuenta' => 'activa'])) {
            return back()->withErrors(['email' => 'No fue posible iniciar sesión con esos datos.'])
                ->withInput($request->only('email'));
        }

        $request->session()->regenerate();
        $request->session()->forget('url.intended');

        if ($destino = $retorno->consumir($request)) {
            return redirect($destino);
        }

        // Destino neutral cuando no existe un curso público elegido en el servidor.
        return redirect()->route('cuenta');
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('inicio')->with('status', 'Has cerrado sesión.');
    }
}
