<?php

namespace App\Services\Soporte;

use LogicException;

/** Permite informar una política ausente sin ocultar fallos técnicos de otra causa. */
final class DecisionOperacionPendiente extends LogicException {}
