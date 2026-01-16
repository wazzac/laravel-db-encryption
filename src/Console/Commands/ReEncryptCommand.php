<?php

namespace Wazza\DbEncrypt\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Wazza\DbEncrypt\Models\EncryptedAttributes;
use Wazza\DbEncrypt\Helper\Encryptor;

class ReEncryptCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'db-encrypt:re-encrypt
                            {--model= : Specific model class to re-encrypt}
                            {--table= : Specific table to re-encrypt}
                            {--batch=100 : Number of records to process at once}
                            {--dry-run : Show what would be re-encrypted without actually doing it}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Re-encrypt all encrypted attributes with a new key (key rotation)';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        if (!config('db-encrypt.key')) {
            $this->error('No encryption key found. Please set DB_ENCRYPT_KEY in your .env file.');
            return self::FAILURE;
        }

        $this->info('Starting re-encryption process...');

        if ($this->option('dry-run')) {
            $this->warn('DRY RUN MODE - No data will be modified');
        }

        $query = EncryptedAttributes::query();

        // Filter by table if specified
        if ($table = $this->option('table')) {
            $query->where('object_type', $table);
            $this->info("Filtering by table: {$table}");
        }

        $total = $query->count();

        if ($total === 0) {
            $this->info('No encrypted attributes found to re-encrypt.');
            return self::SUCCESS;
        }

        $this->info("Found {$total} encrypted attribute(s) to process.");

        if (!$this->option('dry-run') && !$this->confirm('Do you want to proceed with re-encryption?', true)) {
            $this->comment('Re-encryption cancelled.');
            return self::SUCCESS;
        }

        $batchSize = (int) $this->option('batch');
        $processed = 0;
        $errors = 0;

        $bar = $this->output->createProgressBar($total);
        $bar->start();

        $query->chunk($batchSize, function ($records) use (&$processed, &$errors, $bar) {
            foreach ($records as $record) {
                try {
                    if (!$this->option('dry-run')) {
                        // Decrypt with old key, then encrypt with new key
                        $decrypted = Encryptor::decrypt($record->encrypted_value);
                        $encrypted = Encryptor::encrypt($decrypted);
                        $hash = Encryptor::hash($decrypted);

                        $record->update([
                            'encrypted_value' => $encrypted,
                            'hash_index' => $hash,
                        ]);
                    }

                    $processed++;
                } catch (\Exception $e) {
                    $errors++;
                    $this->newLine();
                    $this->error("Error re-encrypting record ID {$record->id}: " . $e->getMessage());
                }

                $bar->advance();
            }
        });

        $bar->finish();
        $this->newLine(2);

        if ($this->option('dry-run')) {
            $this->info("DRY RUN: Would have processed {$processed} record(s).");
        } else {
            $this->info("Successfully re-encrypted {$processed} record(s).");
        }

        if ($errors > 0) {
            $this->warn("Encountered {$errors} error(s) during re-encryption.");
            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
