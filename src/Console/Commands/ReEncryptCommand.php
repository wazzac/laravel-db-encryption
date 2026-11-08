<?php

namespace Wazza\DbEncrypt\Console\Commands;

use Illuminate\Console\Command;
use InvalidArgumentException;
use Wazza\DbEncrypt\Console\Commands\Concerns\ResolvesModelOption;
use Wazza\DbEncrypt\Models\EncryptedAttributes;

class ReEncryptCommand extends Command
{
    use ResolvesModelOption;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'db-encrypt:re-encrypt
                            {--model= : Specific model class to re-encrypt}
                            {--table= : Specific table to re-encrypt}
                            {--batch=100 : Number of records to process at once}
                            {--dry-run : Check every record can be decrypted, without changing anything}
                            {--force : Skip the confirmation prompt}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Re-encrypt all encrypted attributes with the current key and format (key rotation and v1.0 upgrades)';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        if (!config('db-encrypt.key')) {
            $this->error('No encryption key found. Please set DB_ENCRYPT_KEY in your .env file.');
            return self::FAILURE;
        }

        $batchSize = (int) $this->option('batch');
        if ($batchSize < 1) {
            $this->error('The --batch option must be a positive integer.');
            return self::FAILURE;
        }

        try {
            $model = $this->resolveModelOption();
        } catch (InvalidArgumentException $e) {
            $this->error($e->getMessage());
            return self::FAILURE;
        }

        $this->info('Starting re-encryption process...');

        $dryRun = (bool) $this->option('dry-run');
        if ($dryRun) {
            $this->warn('DRY RUN MODE - No data will be modified');
        }

        $query = EncryptedAttributes::query();

        // Filter by model or table if specified
        if ($table = $model?->getTable() ?? $this->option('table')) {
            $query->where('object_type', $table);
            $this->info("Filtering by table: {$table}");
        }

        $total = (clone $query)->count();

        if ($total === 0) {
            $this->info('No encrypted attributes found to re-encrypt.');
            return self::SUCCESS;
        }

        $this->info("Found {$total} encrypted attribute(s) to process.");

        if (!$dryRun && !$this->option('force') && !$this->confirm('Do you want to proceed with re-encryption?', true)) {
            $this->comment('Re-encryption cancelled.');
            return self::SUCCESS;
        }

        $processed = 0;
        $errors = 0;

        $bar = $this->output->createProgressBar($total);
        $bar->start();

        // chunkById, because offset paging is unsafe while the rows are being rewritten
        $query->chunkById($batchSize, function ($records) use (&$processed, &$errors, $bar, $dryRun) {
            foreach ($records as $record) {
                try {
                    // Decrypting tries every configured key, and encrypting always uses the current one
                    $decrypted = $record->decryptValue();

                    if (!$dryRun) {
                        $record->update(EncryptedAttributes::payloadFor(
                            $record->object_type,
                            $record->object_id,
                            $record->attribute,
                            $decrypted
                        ));
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

        if ($dryRun) {
            $this->info("DRY RUN: Would have processed {$processed} record(s).");
        } else {
            $this->info("Successfully re-encrypted {$processed} record(s).");
        }

        if ($errors > 0) {
            $this->warn("Encountered {$errors} error(s) during re-encryption. Keep DB_ENCRYPT_PREVIOUS_KEYS in place until this is resolved.");
            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
