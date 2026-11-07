<?php

namespace Wazza\DbEncrypt\Console\Commands;

use Illuminate\Console\Command;
use Wazza\DbEncrypt\Helper\Encryptor;
use Wazza\DbEncrypt\Models\EncryptedAttributes;

class StatusCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'db-encrypt:status';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Show the encryption configuration and how many stored values still need upgrading (never shows keys or values)';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $info = Encryptor::getInfo();

        $this->components->twoColumnDetail('Cipher', $info['method']);
        $this->components->twoColumnDetail('Cipher available', $info['supported'] ? 'yes' : 'NO');
        $this->components->twoColumnDetail('Key configured', $info['key_configured'] ? 'yes' : 'NO');
        $this->components->twoColumnDetail('Previous keys', (string) $info['previous_keys']);
        $this->components->twoColumnDetail('Legacy v1.0 support', $info['legacy_support'] ? 'on' : 'off');

        $rows = EncryptedAttributes::query()
            ->selectRaw("object_type, attribute, count(*) as total, sum(case when encrypted_value like 'v2:%' then 0 else 1 end) as legacy")
            ->groupBy('object_type', 'attribute')
            ->orderBy('object_type')
            ->orderBy('attribute')
            ->get();

        $this->newLine();
        $this->table(
            ['Table', 'Attribute', 'Values', 'Legacy format'],
            $rows->map(fn ($row) => [$row->object_type, $row->attribute, $row->total, $row->legacy])->all()
        );

        $legacy = (int) $rows->sum('legacy');

        if ($legacy > 0) {
            $this->warn("{$legacy} value(s) use the legacy format. Run db-encrypt:re-encrypt to upgrade them.");
        } elseif ($info['legacy_support']) {
            $this->info('No legacy values remain, so DB_ENCRYPT_LEGACY_SUPPORT can be set to false.');
        }

        return $info['supported'] && $info['key_configured'] ? self::SUCCESS : self::FAILURE;
    }
}
