<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class TestingEnvironmentTest extends TestCase
{
    public function test_database_is_ephemeral_and_no_external_connections_are_registered(): void
    {
        self::assertTrue($this->app->environment('testing'));
        self::assertSame('sqlite', DB::getDefaultConnection());
        self::assertSame(':memory:', DB::connection()->getDatabaseName());
        self::assertSame('sqlite', DB::connection()->getPdo()->getAttribute(\PDO::ATTR_DRIVER_NAME));
        self::assertSame(['sqlite'], array_keys(config('database.connections')));
    }
}
