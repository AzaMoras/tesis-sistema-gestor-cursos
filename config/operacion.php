<?php

return [
    // Decisiones de Fase 02A.1; no se obtienen de formularios ni de fixtures.
    'estados' => [
        'inscripcion.confirmada' => 'confirmada',
        'inscripcion.cancelada' => 'cancelada',
    ],
    'apertura' => [
        'curso' => ['publicado'],
        'edicion' => ['abierta'],
        'grupo' => ['abierta'],
    ],
    // Los nulos históricos no adquieren significado de capacidad ilimitada.
    'cupo_nulo_ilimitado' => false,
    'publicacion' => ['cursos' => 'publicado', 'ediciones' => 'abierta', 'grupos' => 'abierta'],
];
