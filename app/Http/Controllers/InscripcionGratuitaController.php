<?php

namespace App\Http\Controllers;

use App\Services\InscripcionService;
use App\Services\Soporte\DecisionOperacionPendiente;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class InscripcionGratuitaController extends Controller
{
    public function store(Request $request, string $curso, string $grupo, InscripcionService $inscripciones): RedirectResponse
    {
        $cursoId = filter_var($curso, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        $grupoId = filter_var($grupo, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        abort_if($cursoId === false || $grupoId === false, 404);
        $destino = route('cursos.show', $cursoId).'#grupo-'.$grupoId;
        try {
            if (array_diff(array_keys($request->all()), ['_token', 'confirmacion']) !== []) {
                throw ValidationException::withMessages(['solicitud' => 'La solicitud contiene campos no permitidos. Vuelve a confirmar desde el curso.']);
            }
            Validator::make($request->only('confirmacion'), ['confirmacion' => ['required', 'accepted']],
                ['confirmacion.required' => 'Confirma expresamente tu inscripción gratuita al grupo.',
                    'confirmacion.accepted' => 'Debes aceptar la confirmación de inscripción.'])->validate();
            $inscripciones->inscribirGratuita($request->user(), $cursoId, $grupoId);
        } catch (DecisionOperacionPendiente) {
            return redirect($destino)->withErrors(['inscripcion' => 'La institución debe completar una política de inscripción antes de habilitar este grupo. No se guardaron cambios.'])
                ->withInput(['confirmacion' => $request->boolean('confirmacion'), 'grupo_confirmado' => $grupoId]);
        } catch (ValidationException $error) {
            return redirect($destino)->withErrors($error->errors())
                ->withInput(['confirmacion' => $request->boolean('confirmacion'), 'grupo_confirmado' => $grupoId]);
        }

        return redirect()->route('mis-cursos')->with('status', 'Tu inscripción gratuita fue confirmada correctamente.');
    }
}
