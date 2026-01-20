<?php

namespace Wazza\DbEncrypt\Helper;

use Wazza\DbEncrypt\Exceptions\EncryptionException;
use Wazza\DbEncrypt\Exceptions\DecryptionException;
use Wazza\DbEncrypt\Exceptions\InvalidAttributeException;

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
     * @throws EncryptionException
     */
    private static function getKey(?string $manualKey = null): string
    {
        // get the key from the config or .env
        $baseKey = config('db-encrypt.key');
        if (empty($baseKey)) {
            throw EncryptionException::missingKey();
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
     * @throws InvalidAttributeException
     * @throws EncryptionException
     */
    public static function encrypt(?string $plainText, ?string $manualKey = null): string
    {
        // make sure the string to encrypt is not null
        if ($plainText === null) {
            throw InvalidAttributeException::nullInput();
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
            throw EncryptionException::encryptionFailed();
        }

        return base64_encode($iv . $cipherText);
    }

    /**
     * Decrypt a base64-encoded encrypted string.
     *
     * @param string|null $encoded
     * @param string|null $manualKey
     * @return string
     * @throws InvalidAttributeException
     * @throws DecryptionException
     */
    public static function decrypt(?string $encoded, ?string $manualKey = null): string
    {
        // make sure the string to decrypt is not null
        if ($encoded === null) {
            throw InvalidAttributeException::nullInput();
        }

        // base64 decode the input
        $decoded = base64_decode(trim($encoded), true);
        if ($decoded === false) {
            throw DecryptionException::invalidBase64();
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
            throw DecryptionException::decryptionFailed();
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

    /**
     * Encrypt multiple values in batch for performance.
     *
     * @param array $values Associative array where keys are identifiers and values are strings to encrypt
     * @param string|null $manualKey
     * @return array Array with same keys but encrypted values
     * @throws InvalidAttributeException
     * @throws EncryptionException
     */
    public static function encryptBatch(array $values, ?string $manualKey = null): array
    {
        $encrypted = [];

        foreach ($values as $key => $value) {
            if ($value !== null && $value !== '') {
                $encrypted[$key] = self::encrypt($value, $manualKey);
            } else {
                $encrypted[$key] = $value;
            }
        }

        return $encrypted;
    }

    /**
     * Decrypt multiple values in batch for performance.
     *
     * @param array $values Associative array where keys are identifiers and values are encrypted strings
     * @param string|null $manualKey
     * @return array Array with same keys but decrypted values
     * @throws InvalidAttributeException
     * @throws DecryptionException
     */
    public static function decryptBatch(array $values, ?string $manualKey = null): array
    {
        $decrypted = [];

        foreach ($values as $key => $value) {
            if ($value !== null && $value !== '') {
                $decrypted[$key] = self::decrypt($value, $manualKey);
            } else {
                $decrypted[$key] = $value;
            }
        }

        return $decrypted;
    }

    /**
     * Verify if a plain text value matches an encrypted value.
     * Useful for comparing without decrypting.
     *
     * @param string $plainText
     * @param string $encryptedValue
     * @return bool
     */
    public static function verify(string $plainText, string $encryptedValue): bool
    {
        try {
            $decrypted = self::decrypt($encryptedValue);
            return hash_equals($plainText, $decrypted);
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Check if OpenSSL extension is loaded and the cipher method is available.
     *
     * @return bool
     */
    public static function isSupported(): bool
    {
        return extension_loaded('openssl')
            && in_array(self::ENCRYPTION_METHOD, openssl_get_cipher_methods());
    }

    /**
     * Get information about the encryption configuration.
     *
     * @return array
     */
    public static function getInfo(): array
    {
        return [
            'method' => self::ENCRYPTION_METHOD,
            'hash_algorithm' => self::ENCRYPTION_HASH_ALGORITHM,
            'search_hash_algorithm' => self::SEARCH_HASH_ALGORITHM,
            'iv_length' => openssl_cipher_iv_length(self::ENCRYPTION_METHOD),
            'supported' => self::isSupported(),
            'key_configured' => !empty(config('db-encrypt.key')),
        ];
    }
}
