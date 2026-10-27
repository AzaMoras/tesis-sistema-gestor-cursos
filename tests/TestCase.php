<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use LogicException;

abstract class TestCase extends BaseTestCase
{
    public function createApplication()
    {
        // El caché de configuración podría contener una conexión de desarrollo.
        // Se rechaza antes del bootstrap y antes de que RefreshDatabase actúe.
        if (is_file(dirname(__DIR__).'/bootstrap/cache/config.php')) {
            throw new LogicException('No ejecutes tests con configuración cacheada; usa un checkout de testing sin ese caché.');
        }

        $app = parent::createApplication();
        if (! $app->environment('testing')
            || config('database.default') !== 'sqlite'
            || config('database.connections.sqlite.database') !== ':memory:'
            || ! empty(config('database.connections.sqlite.url'))) {
            throw new LogicException('Suite detenida: solo se permite testing con SQLite :memory:.');
        }

        // Incluso una conexión explícita de otro nombre queda fuera de la suite.
        config(['database.connections' => ['sqlite' => config('database.connections.sqlite')]]);

        return $app;
    }
}
