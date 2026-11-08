<?php

namespace Wazza\DbEncrypt\Providers;

use Illuminate\Support\ServiceProvider as BaseServiceProvider;
use Wazza\DbEncrypt\Helper\Encryptor;
use Wazza\DbEncrypt\Http\Controllers\DbEncryptController;

class DbEncryptServiceProvider extends BaseServiceProvider
{
    /**
     * Bootstrap services.
     * Allows us to run: php artisan vendor:publish --tag=db-encrypt-config
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

        // Register Artisan commands
        if ($this->app->runningInConsole()) {
            $this->commands([
                \Wazza\DbEncrypt\Console\Commands\GenerateKeyCommand::class,
                \Wazza\DbEncrypt\Console\Commands\ReEncryptCommand::class,
                \Wazza\DbEncrypt\Console\Commands\PruneCommand::class,
                \Wazza\DbEncrypt\Console\Commands\RenameObjectTypeCommand::class,
                \Wazza\DbEncrypt\Console\Commands\StatusCommand::class,
            ]);
        }
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

        // The controller holds the current model, so every resolve gets its own instance.
        // A shared one would keep the last model alive for the life of a queue worker or Octane.
        $this->app->bind(DbEncryptController::class, function () {
            return new DbEncryptController();
        });

        // Register the Encryptor as a singleton for the facade
        $this->app->singleton('db-encrypt', function () {
            return new Encryptor();
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
