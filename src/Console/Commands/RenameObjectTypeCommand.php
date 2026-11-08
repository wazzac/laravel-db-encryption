<?php

namespace Wazza\DbEncrypt\Console\Commands;

use Illuminate\Console\Command;
use Wazza\DbEncrypt\Models\EncryptedAttributes;

class RenameObjectTypeCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'db-encrypt:rename-table
                            {from : The old table name}
                            {to : The new table name}
                            {--batch=100 : Number of records to process at once}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Move encrypted attributes to a new table name (values are bound to their table, so a plain SQL update would break them)';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $from = $this->argument('from');
        $to = $this->argument('to');
        $batchSize = (int) $this->option('batch');

        if ($batchSize < 1) {
            $this->error('The --batch option must be a positive integer.');
            return self::FAILURE;
        }

        if ($from === $to) {
            $this->error('The old and new table names are the same.');
            return self::FAILURE;
        }

        // Moved rows no longer match $from, so an interrupted run can simply be started again
        $moved = 0;
        $connection = (new EncryptedAttributes())->getConnection();

        EncryptedAttributes::where('object_type', $from)
            ->chunkById($batchSize, function ($records) use ($connection, $to, &$moved) {
                $connection->transaction(function () use ($records, $to, &$moved) {
                    foreach ($records as $record) {
                        $plainText = $record->decryptValue();

                        $record->update([
                            'object_type' => $to,
                            ...EncryptedAttributes::payloadFor($to, $record->object_id, $record->attribute, $plainText),
                        ]);

                        $moved++;
                    }
                });
            });

        $this->info("Moved {$moved} encrypted attribute(s) from '{$from}' to '{$to}'.");

        return self::SUCCESS;
    }
}
