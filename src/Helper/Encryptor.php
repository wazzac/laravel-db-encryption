<?php

namespace Wazza\DbEncrypt\Helper;

use RuntimeException;
use InvalidArgumentException;

final class Encryptor
{
    private const ENCRYPTION_METHOD = 'AES-256-CBC';
    private const ENCRYPTION_OPTIONS = OPENSSL_RAW_DATA;
    private const ENCRYPTION_HASH_ALGORITHM = 'sha512';
    private const ENCRYPTION_HASH_BINARY = false; // false = hex output
    private const SEARCH_HASH_ALGORITHM = 'sha256'; // used for indexing

    /**
     * Get the derived encryption key.
     *
     * @param string|null $manualKey Optional key to customize encryption per record.
     * @return string
     */
    private static function getKey(?string $manualKey = null): string
    {
        // get the key from the config or .env
        $baseKey = config('db-encrypt.key');
        if (empty($baseKey)) {
            throw new RuntimeException('Encryption key is not set in config or .env.');
        }

        // ensure the key is a valid string
        return hash(
            self::ENCRYPTION_HASH_ALGORITHM,
            $manualKey ? $baseKey . ":" . trim($manualKey) : $baseKey,
            self::ENCRYPTION_HASH_BINARY
        );
    }

    /**
     * Encrypt a string using OpenSSL with base64-encoded result.
     *
     * @param string|null $plainText
     * @param string|null $manualKey
     * @return string
     */
    public static function encrypt(?string $plainText, ?string $manualKey = null): string
    {
        // make sure the string to encrypt is not null
        if ($plainText === null) {
            throw new InvalidArgumentException('String to encrypt cannot be null.');
        }

        $iv = random_bytes(openssl_cipher_iv_length(self::ENCRYPTION_METHOD));

        $cipherText = openssl_encrypt(
            $plainText,
            self::ENCRYPTION_METHOD,
            self::getKey($manualKey),
            self::ENCRYPTION_OPTIONS,
            $iv
        );

        if ($cipherText === false) {
            throw new RuntimeException('Encryption failed.');
        }

        return base64_encode($iv . $cipherText);
    }

    /**
     * Decrypt a base64-encoded encrypted string.
     *
     * @param string|null $encoded
     * @param string|null $manualKey
     * @return string
     */
    public static function decrypt(?string $encoded, ?string $manualKey = null): string
    {
        // make sure the string to decrypt is not null
        if ($encoded === null) {
            throw new InvalidArgumentException('String to decrypt cannot be null.');
        }

        // base64 decode the input
        $decoded = base64_decode(trim($encoded), true);
        if ($decoded === false) {
            throw new RuntimeException('Base64 decoding failed.');
        }

        $ivLength = openssl_cipher_iv_length(self::ENCRYPTION_METHOD);
        $iv = substr($decoded, 0, $ivLength);
        $cipherText = substr($decoded, $ivLength);

        // decrypt to plain text
        $plainText = openssl_decrypt(
            $cipherText,
            self::ENCRYPTION_METHOD,
            self::getKey($manualKey),
            self::ENCRYPTION_OPTIONS,
            $iv
        );

        if ($plainText === false) {
            throw new RuntimeException('Decryption failed.');
        }

        return $plainText;
    }

    /**
     * Generate a SHA-256 hash of the given value for indexing.
     *
     * @param string $value
     * @return string
     */
    public static function hash(string $value): string
    {
        return hash(
            self::SEARCH_HASH_ALGORITHM,
            trim($value)
        );
    }
}
