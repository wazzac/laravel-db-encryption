<?php

namespace Wazza\DbEncrypt\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Str;

class GenerateKeyCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'db-encrypt:generate-key
                            {--show : Display the key instead of modifying files}
                            {--force : Force the operation to run when in production}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate a new encryption key for database encryption';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $key = $this->generateRandomKey();

        if ($this->option('show')) {
            $this->line('<comment>' . $key . '</comment>');
            return self::SUCCESS;
        }

        if (!$this->setKeyInEnvironmentFile($key)) {
            return self::FAILURE;
        }

        $this->info('Database encryption key generated successfully.');
        $this->comment('The key has been set in your .env file as DB_ENCRYPT_KEY');

        return self::SUCCESS;
    }

    /**
     * Generate a random key for encryption.
     *
     * @return string
     */
    protected function generateRandomKey(): string
    {
        return base64_encode(random_bytes(32));
    }

    /**
     * Set the encryption key in the environment file.
     *
     * @param string $key
     * @return bool
     */
    protected function setKeyInEnvironmentFile(string $key): bool
    {
        $currentKey = config('db-encrypt.key');

        if (strlen($currentKey) !== 0 && (!$this->confirm('This will invalidate all existing encrypted data. Do you wish to continue?'))) {
            $this->comment('Key generation cancelled.');
            return false;
        }

        if (!$this->writeNewEnvironmentFileWith($key)) {
            $this->error('Unable to update .env file.');
            return false;
        }

        return true;
    }

    /**
     * Write a new environment file with the given key.
     *
     * @param string $key
     * @return bool
     */
    protected function writeNewEnvironmentFileWith(string $key): bool
    {
        $envPath = base_path('.env');

        if (!file_exists($envPath)) {
            $this->error('.env file not found.');
            return false;
        }

        $content = file_get_contents($envPath);

        $replaced = preg_replace(
            '/^DB_ENCRYPT_KEY=.*$/m',
            'DB_ENCRYPT_KEY=' . $key,
            $content,
            -1,
            $count
        );

        // If the key wasn't found, append it
        if ($count === 0) {
            $replaced = $content . PHP_EOL . 'DB_ENCRYPT_KEY=' . $key;
        }

        file_put_contents($envPath, $replaced);

        return true;
    }
}
