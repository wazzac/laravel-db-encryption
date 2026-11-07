<?php

namespace Wazza\DbEncrypt\Tests;

use Orchestra\Testbench\TestCase as OrchestraTestCase;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Wazza\DbEncrypt\Providers\DbEncryptServiceProvider;

abstract class TestCase extends OrchestraTestCase
{
    use DatabaseMigrations;

    /**
     * Add the package provider
     *
     * @param $app
     * @return array
     */
    protected function getPackageProviders($app)
    {
        return [
            DbEncryptServiceProvider::class,
        ];
    }

    /**
     * Define environment setup.
     *
     * @param  \Illuminate\Foundation\Application $app
     * @return void
     */
    protected function getEnvironmentSetUp($app)
    {
        $app['config']->set('database.default', 'testdb');
        $app['config']->set('database.connections.testdb', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);
        $app['config']->set('db-encrypt.db.primary_key_format', env('DB_ENCRYPT_DB_PRIMARY_KEY_FORMAT', 'int'));
        $app['config']->set('db-encrypt.logging.level', env('DB_ENCRYPT_LOG_LEVEL'));
        $app['config']->set('db-encrypt.logging.indicator', env('DB_ENCRYPT_LOG_INDICATOR'));
        $app['config']->set('db-encrypt.key', env('DB_ENCRYPT_KEY'));
    }
}
