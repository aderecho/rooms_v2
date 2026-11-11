<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    public function createApplication()
    {
        $app = parent::createApplication();
        $connection = $app['config']->get('database.default');
        $database = $app['config']->get("database.connections.{$connection}");

        if (! $app->environment('testing')
            || ($database['driver'] ?? null) !== 'sqlite'
            || ($database['database'] ?? null) !== ':memory:') {
            throw new \RuntimeException(
                'Tests require the testing environment and SQLite :memory:. '
                .'Cached or non-test database configuration detected; refusing to reset the database.'
            );
        }

        return $app;
    }
}
