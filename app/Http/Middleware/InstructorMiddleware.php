<?php

namespace App\Http\Middleware;

class InstructorMiddleware extends RolMiddleware
{
    protected const ROLES = ['instructor'];
}
