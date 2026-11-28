<?php

namespace App\Exceptions;

final class MensajesPublicos403
{
    public const GENERICO = 'No tienes acceso a esta página o acción.';

    public const AULA = [
        'El aula requiere una inscripción confirmada y no cancelada.',
        'El curso o su institución no están disponibles. Tu historial se conserva.',
        'El acceso no está activado o ya venció. Puedes conservar y consultar tu historial.',
        'La configuración de vigencia está incompleta. Contacta a la institución.',
    ];
}
