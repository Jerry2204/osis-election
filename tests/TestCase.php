<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    public function createApplication()
    {
        $app = parent::createApplication();
        $database = $app['config']->get('database.connections.mysql.database');
        if ($app['config']->get('database.default') !== 'mysql' || ! str_ends_with((string) $database, '_testing')) {
            throw new \RuntimeException('Pengujian hanya diizinkan pada MySQL dengan nama database berakhiran _testing. Siapkan .env.testing dan jalankan config:clear.');
        }
        return $app;
    }
}
