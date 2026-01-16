<?php

namespace Wazza\DbEncrypt\Providers;

use Illuminate\Support\ServiceProvider as BaseServiceProvider;
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

        // Register the singleton service the package provides.
        $this->app->singleton(DbEncryptController::class, function () {
            return new DbEncryptController();
        });

        // Register the Encryptor as a singleton for the facade
        $this->app->singleton('db-encrypt', function () {
            return new \Wazza\DbEncrypt\Helper\Encryptor();
        });

        /*
        You can use the above registered singleton service in your application like this:
        $dnEncryptController = app(DbEncryptController::class);
        $dnEncryptController->someMethod();

        You can also use dependency injection in your controllers or other services.
        For example:
        public function __construct(DbEncryptController $dnEncryptController)
        {
            $this->dnEncryptController = $dnEncryptController;
        }

        This allows you to access the methods of DbEncryptController within your class.
        You can also register other services or bindings as needed.
        $this->app->bind('some.service', function ($app) {
            return new SomeService();
        });

        For direct encryption/decryption you can use the facade:
        use Wazza\DbEncrypt\Facades\DbEncrypt;
        $encrypted = DbEncrypt::encrypt('sensitive data');
        $decrypted = DbEncrypt::decrypt($encrypted);
        */
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
