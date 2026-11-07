<?php

namespace Wazza\DbEncrypt\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Wazza\DbEncrypt\Console\Commands\Concerns\ResolvesModelOption;
use Wazza\DbEncrypt\Models\EncryptedAttributes;

class PruneCommand extends Command
{
    use ResolvesModelOption;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'db-encrypt:prune
                            {--model= : Specific model class to prune (uses its table, primary key and connection)}
                            {--table= : Specific table to prune orphaned records from}
                            {--key=id : Primary key column of the parent table (ignored with --model)}
                            {--batch=500 : Number of records to check at once}
                            {--dry-run : Show what would be deleted without actually deleting}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Remove orphaned encrypted attributes (where the parent model no longer exists)';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
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

        $this->info('Scanning for orphaned encrypted attributes...');

        $dryRun = (bool) $this->option('dry-run');
        if ($dryRun) {
            $this->warn('DRY RUN MODE - No data will be deleted');
        }

        // Filter by model or table if specified
        if ($target = $model?->getTable() ?? $this->option('table')) {
            $this->info("Filtering by table: {$target}");
            $tables = [$target];
        } else {
            $tables = EncryptedAttributes::query()->distinct()->pluck('object_type')->all();
        }

        $keyName = $model?->getKeyName() ?? $this->option('key');
        $connection = $model?->getConnectionName();
        $orphanedCount = 0;

        foreach ($tables as $table) {
            $this->info("Checking table: {$table}");

            try {
                $count = $this->pruneTable($table, $keyName, $connection, $batchSize, $dryRun);
            } catch (\Exception $e) {
                // Nothing can be proven orphaned on a table we can't read, so leave its rows alone
                $this->warn("Could not check table '{$table}': " . $e->getMessage());
                continue;
            }

            if ($count > 0) {
                $orphanedCount += $count;
                $this->comment("Found {$count} orphaned record(s) in table '{$table}'");
            }
        }

        $this->newLine();

        if ($orphanedCount === 0) {
            $this->info('No orphaned encrypted attributes found.');
        } elseif ($dryRun) {
            $this->info("DRY RUN: Would have deleted {$orphanedCount} orphaned record(s).");
        } else {
            $this->info("Successfully deleted {$orphanedCount} orphaned record(s).");
        }

        return self::SUCCESS;
    }

    /**
     * Delete the orphaned rows of one table, a batch at a time.
     *
     * @param string $table
     * @param string $keyName
     * @param string|null $connection
     * @param int $batchSize
     * @param bool $dryRun
     * @return int The number of orphaned rows found
     */
    private function pruneTable(string $table, string $keyName, ?string $connection, int $batchSize, bool $dryRun): int
    {
        $count = 0;

        EncryptedAttributes::query()
            ->select(['id', 'object_id'])
            ->where('object_type', $table)
            ->chunkById($batchSize, function ($records) use ($table, $keyName, $connection, $dryRun, &$count) {
                $existing = DB::connection($connection)->table($table)
                    ->whereIn($keyName, $records->pluck('object_id')->unique()->values())
                    ->pluck($keyName)
                    ->map(fn ($key) => (string) $key)
                    ->flip();

                $orphanedIds = $records
                    ->reject(fn ($record) => $existing->has((string) $record->object_id))
                    ->pluck('id');

                $count += $orphanedIds->count();

                if (!$dryRun && $orphanedIds->isNotEmpty()) {
                    EncryptedAttributes::whereIn('id', $orphanedIds)->delete();
                }
            });

        return $count;
    }
}
