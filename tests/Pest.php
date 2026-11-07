<?php

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind a different classes or traits.
|
*/

uses(\Wazza\DbEncrypt\Tests\TestCase::class)->in('Unit', 'Feature');

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
*/

/**
 * Encrypt a value exactly as v1.0.x did. This is a frozen copy of the old format, so that
 * tests prove data written by v1.0.x stays readable. Do not "fix" or modernize it.
 */
function legacyEncrypt(string $plainText, ?string $key = null): string
{
    $iv = random_bytes(16);
    $derivedKey = hash('sha512', $key ?? config('db-encrypt.key'), false);

    return base64_encode($iv . openssl_encrypt($plainText, 'AES-256-CBC', $derivedKey, OPENSSL_RAW_DATA, $iv));
}

/**
 * Hash a value exactly as v1.0.x did.
 */
function legacyHash(string $value): string
{
    return hash('sha256', trim($value));
}
