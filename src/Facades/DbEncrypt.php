<?php

namespace Wazza\DbEncrypt\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static string encrypt(string|null $plainText, string|null $manualKey = null, string $context = '')
 * @method static string decrypt(string|null $encoded, string|null $manualKey = null, string $context = '')
 * @method static string hash(string $value, string|null $context = null)
 * @method static array hashCandidates(string $value, string|null $context = null)
 * @method static string attributeContext(string $table, int|string $id, string $attribute)
 * @method static string searchContext(string $table, string $attribute)
 * @method static array encryptBatch(array $values, string|null $manualKey = null)
 * @method static array decryptBatch(array $values, string|null $manualKey = null)
 * @method static bool verify(string $plainText, string $encryptedValue, string|null $manualKey = null, string $context = '')
 * @method static bool isLegacyPayload(string $encoded)
 * @method static bool legacySupportEnabled()
 * @method static bool isSupported()
 * @method static array getInfo()
 *
 * @see \Wazza\DbEncrypt\Helper\Encryptor
 */
class DbEncrypt extends Facade
{
    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor(): string
    {
        return 'db-encrypt';
    }
}
