<?php

namespace Wazza\DbEncrypt\Tests;

use Orchestra\Testbench\TestCase as OrchestraTestCase;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Wazza\DbEncrypt\Providers\DbEncryptServiceProvider;
use Wazza\DbEncrypt\Models\EncryptedAttributes;

abstract class TestCase extends OrchestraTestCase
{
    use DatabaseMigrations, DatabaseTransactions;

    /**
     * Setup the test environment.
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
        // $this->withFactories(__DIR__ . '/../database/factories');
    }

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
            'database' => __DIR__ . '/testdb.sqlite',
            'prefix' => '',
        ]);
        $app['config']->set('db-encrypt.db.primary_key_format', env('DB_ENCRYPT_DB_PRIMARY_KEY_FORMAT', 'int'));
        $app['config']->set('db-encrypt.logging.level', env('DB_ENCRYPT_LOG_LEVEL'));
        $app['config']->set('db-encrypt.logging.indicator', env('DB_ENCRYPT_LOG_INDICATOR'));
        $app['config']->set('db-encrypt.key', env('DB_ENCRYPT_KEY'));
    }

    /**
     * Define aliases for the package
     */
    protected function getPackageAliases($app)
    {
        return [
            'config' => 'Illuminate\Config\Repository'
        ];
    }
}
