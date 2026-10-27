<?php

namespace App\Http\Middleware;

class CoordinadorMiddleware extends RolMiddleware
{
    protected const ROLES = ['coordinador'];
}
