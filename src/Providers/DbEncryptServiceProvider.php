<?php

namespace Wazza\DbEncrypt\Providers;

use Illuminate\Support\ServiceProvider as BaseServiceProvider;
use Wazza\DbEncrypt\Http\Controllers\DnEncryptController;

class DbEncryptServiceProvider extends BaseServiceProvider
{
    /**
     * Bootstrap services.
     * Allows us to run: // php artisan vendor:publish --tag=db-encrypt-config
     */
    public function boot(): void
    {
        // Publish required config files
        $this->publishes([
            $this->configPath() => config_path('db-encrypt.php'),
        ], 'db-encrypt-config');

        // Publish migration files
        $this->publishes([
            $this->dbMigrationsPath() => database_path('migrations')
        ], 'db-encrypt-migrations');

        // Load the migrations
        $this->loadMigrationsFrom($this->dbMigrationsPath());
    }

    /**
     * Register services.
     */
    public function register(): void
    {
        // Merge the default config path
        $this->mergeConfigFrom(
            $this->configPath(),
            'db-encrypt'
        );

        // Register the singleton service the package provides.
        $this->app->singleton(DnEncryptController::class, function () {
            return new DnEncryptController();
        });
    }

    /**
     * Set the config path
     *
     * @return string
     */
    private function configPath(): string
    {
        return __DIR__ . '/../../config/db-encrypt.php';
    }

    /**
     * Set the db migration path
     *
     * @return string
     */
    private function dbMigrationsPath(): string
    {
        return __DIR__ . '/../../database/migrations';
    }
}
