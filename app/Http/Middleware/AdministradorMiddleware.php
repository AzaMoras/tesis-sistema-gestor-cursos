<?php

namespace App\Http\Middleware;

class AdministradorMiddleware extends RolMiddleware
{
    protected const ROLES = ['administrador'];
}
