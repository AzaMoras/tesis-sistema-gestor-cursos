<?php

namespace App\Http\Middleware;

class ParticipanteMiddleware extends RolMiddleware
{
    protected const ROLES = ['participante'];
}
