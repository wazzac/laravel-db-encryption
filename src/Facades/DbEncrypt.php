<?php

namespace Wazza\DbEncrypt\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static string encrypt(string $plainText, string|null $manualKey = null)
 * @method static string decrypt(string $encoded, string|null $manualKey = null)
 * @method static string hash(string $value)
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
