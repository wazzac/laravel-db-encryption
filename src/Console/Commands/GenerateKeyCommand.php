<?php

namespace Wazza\DbEncrypt\Console\Commands;

use Illuminate\Console\Command;

class GenerateKeyCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'db-encrypt:generate-key
                            {--show : Display the key instead of modifying files}
                            {--rotate : Keep the current key as a previous key, so existing data stays readable}
                            {--force : Replace an existing key without asking (existing data becomes unreadable)}';

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

        $currentKey = (string) config('db-encrypt.key');
        $rotating = $this->option('rotate') && $currentKey !== '';

        if (
            $currentKey !== '' && !$rotating && !$this->option('force')
            && !$this->confirm('This will make all existing encrypted data unreadable (use --rotate to keep it). Do you wish to continue?')
        ) {
            $this->comment('Key generation cancelled.');
            return self::FAILURE;
        }

        $values = ['DB_ENCRYPT_KEY' => $key];
        if ($rotating) {
            $previousKeys = array_filter((array) config('db-encrypt.previous_keys', []));
            $values['DB_ENCRYPT_PREVIOUS_KEYS'] = implode(',', array_unique([$currentKey, ...$previousKeys]));
        }

        if (!$this->writeEnvironmentValues($values)) {
            return self::FAILURE;
        }

        $this->info('Database encryption key generated successfully.');
        $this->comment('The key has been set in your .env file as DB_ENCRYPT_KEY');

        if ($rotating) {
            $this->comment('The old key is in DB_ENCRYPT_PREVIOUS_KEYS. Run db-encrypt:re-encrypt, then remove it.');
        }

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
     * Write values to the environment file, replacing any that already exist.
     *
     * @param array<string, string> $values
     * @return bool
     */
    protected function writeEnvironmentValues(array $values): bool
    {
        $envPath = $this->laravel->environmentFilePath();

        if (!file_exists($envPath)) {
            $this->error('.env file not found.');
            return false;
        }

        $content = file_get_contents($envPath);
        $eol = str_contains($content, "\r\n") ? "\r\n" : "\n";

        foreach ($values as $name => $value) {
            // [^\r\n] rather than ".", so CRLF files keep their line endings
            $line = $name . '=' . $value;
            $content = preg_replace_callback('/^' . $name . '=[^\r\n]*/m', fn () => $line, $content, -1, $count);

            // If the key wasn't found, append it
            if ($count === 0) {
                $content = rtrim($content, "\r\n") . $eol . $line . $eol;
            }
        }

        if (file_put_contents($envPath, $content) === false) {
            $this->error('Unable to update .env file.');
            return false;
        }

        return true;
    }
}
