<?php

namespace Wazza\DbEncrypt\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Wazza\DbEncrypt\Models\EncryptedAttributes;

class PruneCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'db-encrypt:prune
                            {--model= : Specific model class to prune}
                            {--table= : Specific table to prune orphaned records from}
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
        $this->info('Scanning for orphaned encrypted attributes...');

        if ($this->option('dry-run')) {
            $this->warn('DRY RUN MODE - No data will be deleted');
        }

        $query = EncryptedAttributes::query();

        // Filter by table if specified
        if ($table = $this->option('table')) {
            $query->where('object_type', $table);
            $this->info("Filtering by table: {$table}");
        }

        $orphanedCount = 0;
        $processedTables = [];

        // Group by table/model type
        $groupedRecords = $query->get()->groupBy('object_type');

        foreach ($groupedRecords as $table => $records) {
            $this->info("Checking table: {$table}");

            // Check if records exist in the actual table
            $orphanedIds = [];

            foreach ($records as $record) {
                try {
                    $exists = DB::table($table)->where('id', $record->object_id)->exists();

                    if (!$exists) {
                        $orphanedIds[] = $record->id;
                    }
                } catch (\Exception $e) {
                    $this->warn("Could not check table '{$table}': " . $e->getMessage());
                    continue 2; // Skip this table
                }
            }

            if (count($orphanedIds) > 0) {
                $count = count($orphanedIds);
                $orphanedCount += $count;
                $this->comment("Found {$count} orphaned record(s) in table '{$table}'");

                if (!$this->option('dry-run')) {
                    EncryptedAttributes::whereIn('id', $orphanedIds)->delete();
                    $this->info("Deleted {$count} orphaned record(s)");
                }
            }

            $processedTables[] = $table;
        }

        $this->newLine();

        if ($orphanedCount === 0) {
            $this->info('No orphaned encrypted attributes found.');
        } else {
            if ($this->option('dry-run')) {
                $this->info("DRY RUN: Would have deleted {$orphanedCount} orphaned record(s).");
            } else {
                $this->info("Successfully deleted {$orphanedCount} orphaned record(s).");
            }
        }

        return self::SUCCESS;
    }
}
