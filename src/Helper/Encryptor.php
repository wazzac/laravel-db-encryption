<?php

namespace Wazza\DbEncrypt\Helper;

use Wazza\DbEncrypt\Exceptions\EncryptionException;
use Wazza\DbEncrypt\Exceptions\DecryptionException;
use Wazza\DbEncrypt\Exceptions\InvalidAttributeException;

/**
 * Low-level encryption primitives.
 *
 * Current payload format: "v2:" . base64(iv . tag . ciphertext), using AES-256-GCM with a
 * 256-bit key derived through HKDF-SHA256. Optional context is authenticated (GCM AAD).
 *
 * Legacy payload format (v1.0.x): base64(iv . ciphertext), using AES-256-CBC with no
 * authentication. It is only ever read, and only while `db-encrypt.legacy_support` is on.
 * Base64 has no ":" so the two formats can't be confused.
 */
final class Encryptor
{
    private const ENCRYPTION_METHOD = 'aes-256-gcm';
    private const PAYLOAD_PREFIX = 'v2:';
    private const IV_LENGTH = 12;
    private const TAG_LENGTH = 16;
    private const KEY_LENGTH = 32;
    private const KDF_ALGORITHM = 'sha256';
    private const SEARCH_HASH_ALGORITHM = 'sha256'; // used as an HMAC for indexing

    private const LEGACY_METHOD = 'AES-256-CBC';
    private const LEGACY_IV_LENGTH = 16;
    private const LEGACY_KEY_HASH_ALGORITHM = 'sha512';
    private const LEGACY_SEARCH_HASH_ALGORITHM = 'sha256';

    /**
     * Get the configured keys, with the current key first.
     *
     * @return array<int, string>
     * @throws EncryptionException
     */
    private static function keys(): array
    {
        $currentKey = config('db-encrypt.key');
        if (empty($currentKey)) {
            throw EncryptionException::missingKey();
        }

        $previousKeys = array_filter((array) config('db-encrypt.previous_keys', []));

        return array_values(array_unique([$currentKey, ...$previousKeys]));
    }

    /**
     * Derive a purpose-bound 256-bit key, so encryption and search never share key material.
     *
     * @param string $baseKey
     * @param string $purpose
     * @param string|null $manualKey
     * @return string Raw binary key
     */
    private static function deriveKey(string $baseKey, string $purpose, ?string $manualKey = null): string
    {
        return hash_hkdf(
            self::KDF_ALGORITHM,
            $baseKey,
            self::KEY_LENGTH,
            'db-encrypt:v2:' . $purpose . ':' . trim((string) $manualKey)
        );
    }

    /**
     * Encrypt a string with AES-256-GCM.
     *
     * @param string|null $plainText
     * @param string|null $manualKey Optional key to customize encryption per record.
     * @param string $context Authenticated data; the same value must be supplied to decrypt.
     * @return string
     * @throws InvalidAttributeException
     * @throws EncryptionException
     */
    public static function encrypt(?string $plainText, ?string $manualKey = null, string $context = ''): string
    {
        // make sure the string to encrypt is not null
        if ($plainText === null) {
            throw InvalidAttributeException::nullInput();
        }

        $iv = random_bytes(self::IV_LENGTH);
        $tag = '';

        $cipherText = openssl_encrypt(
            $plainText,
            self::ENCRYPTION_METHOD,
            self::deriveKey(self::keys()[0], 'encrypt', $manualKey),
            OPENSSL_RAW_DATA,
            $iv,
            $tag,
            $context,
            self::TAG_LENGTH
        );

        if ($cipherText === false) {
            throw EncryptionException::encryptionFailed();
        }

        return self::PAYLOAD_PREFIX . base64_encode($iv . $tag . $cipherText);
    }

    /**
     * Decrypt a payload, trying the current key and then any previous keys.
     *
     * @param string|null $encoded
     * @param string|null $manualKey
     * @param string $context The context the value was encrypted with. Ignored by legacy payloads.
     * @return string
     * @throws InvalidAttributeException
     * @throws DecryptionException
     * @throws EncryptionException If no key is configured
     */
    public static function decrypt(?string $encoded, ?string $manualKey = null, string $context = ''): string
    {
        // make sure the string to decrypt is not null
        if ($encoded === null) {
            throw InvalidAttributeException::nullInput();
        }

        $encoded = trim($encoded);

        return self::isLegacyPayload($encoded)
            ? self::decryptLegacy($encoded, $manualKey)
            : self::decryptCurrent($encoded, $manualKey, $context);
    }

    /**
     * Decrypt a v2 (AES-256-GCM) payload.
     *
     * @param string $encoded
     * @param string|null $manualKey
     * @param string $context
     * @return string
     * @throws DecryptionException
     */
    private static function decryptCurrent(string $encoded, ?string $manualKey, string $context): string
    {
        $decoded = base64_decode(substr($encoded, strlen(self::PAYLOAD_PREFIX)), true);
        if ($decoded === false) {
            throw DecryptionException::invalidBase64();
        }

        // the ciphertext may legitimately be empty, the IV and tag may not
        if (strlen($decoded) < self::IV_LENGTH + self::TAG_LENGTH) {
            throw DecryptionException::invalidPayload();
        }

        $iv = substr($decoded, 0, self::IV_LENGTH);
        $tag = substr($decoded, self::IV_LENGTH, self::TAG_LENGTH);
        $cipherText = substr($decoded, self::IV_LENGTH + self::TAG_LENGTH);

        foreach (self::keys() as $baseKey) {
            $plainText = openssl_decrypt(
                $cipherText,
                self::ENCRYPTION_METHOD,
                self::deriveKey($baseKey, 'encrypt', $manualKey),
                OPENSSL_RAW_DATA,
                $iv,
                $tag,
                $context
            );

            if ($plainText !== false) {
                return $plainText;
            }
        }

        throw DecryptionException::decryptionFailed();
    }

    /**
     * Decrypt a v1.0.x (AES-256-CBC) payload. Kept for data written before v1.1.0.
     *
     * @param string $encoded
     * @param string|null $manualKey
     * @return string
     * @throws DecryptionException
     */
    private static function decryptLegacy(string $encoded, ?string $manualKey): string
    {
        // checked first: with support off, a forged CBC row can't reach the unauthenticated cipher
        if (!self::legacySupportEnabled()) {
            throw DecryptionException::legacyDisabled();
        }

        $decoded = base64_decode($encoded, true);
        if ($decoded === false) {
            throw DecryptionException::invalidBase64();
        }

        // CBC with padding always produces at least one block after the IV
        if (strlen($decoded) < self::LEGACY_IV_LENGTH * 2) {
            throw DecryptionException::invalidPayload();
        }

        $iv = substr($decoded, 0, self::LEGACY_IV_LENGTH);
        $cipherText = substr($decoded, self::LEGACY_IV_LENGTH);

        foreach (self::keys() as $baseKey) {
            // v1.0.x key derivation, including OpenSSL's truncation of the hex digest. Do not change.
            $key = hash(
                self::LEGACY_KEY_HASH_ALGORITHM,
                $manualKey ? $baseKey . ':' . trim($manualKey) : $baseKey,
                false
            );

            $plainText = openssl_decrypt($cipherText, self::LEGACY_METHOD, $key, OPENSSL_RAW_DATA, $iv);

            if ($plainText !== false) {
                return $plainText;
            }
        }

        throw DecryptionException::decryptionFailed();
    }

    /**
     * Generate a keyed search hash (HMAC-SHA256) of the given value for indexing.
     * Surrounding whitespace is ignored. The value is otherwise matched exactly.
     *
     * @param string $value
     * @param string|null $context Scope of the hash, so equal values in different scopes differ.
     * @return string 64 hex characters
     * @throws EncryptionException If no key is configured
     */
    public static function hash(string $value, ?string $context = null): string
    {
        return self::hashWithKey(self::keys()[0], $value, $context);
    }

    /**
     * Get every hash a stored row may hold for this value: the current key's, each previous
     * key's and, while legacy support is on, the v1.0.x unkeyed hash.
     *
     * @param string $value
     * @param string|null $context
     * @return array<int, string>
     * @throws EncryptionException If no key is configured
     */
    public static function hashCandidates(string $value, ?string $context = null): array
    {
        $hashes = array_map(
            fn (string $baseKey) => self::hashWithKey($baseKey, $value, $context),
            self::keys()
        );

        if (self::legacySupportEnabled()) {
            $hashes[] = hash(self::LEGACY_SEARCH_HASH_ALGORITHM, trim($value));
        }

        return $hashes;
    }

    /**
     * Compute the HMAC for one key.
     *
     * @param string $baseKey
     * @param string $value
     * @param string|null $context
     * @return string
     */
    private static function hashWithKey(string $baseKey, string $value, ?string $context): string
    {
        return hash_hmac(
            self::SEARCH_HASH_ALGORITHM,
            (string) $context . "\0" . trim($value),
            self::deriveKey($baseKey, 'search')
        );
    }

    /**
     * Build the context that binds a stored value to its row, so a ciphertext copied onto a
     * different row fails to decrypt.
     *
     * @param string $table
     * @param int|string $id
     * @param string $attribute
     * @return string
     */
    public static function attributeContext(string $table, int|string $id, string $attribute): string
    {
        return implode("\0", [$table, (string) $id, $attribute]);
    }

    /**
     * Build the context that scopes a search hash to a table and attribute.
     *
     * @param string $table
     * @param string $attribute
     * @return string
     */
    public static function searchContext(string $table, string $attribute): string
    {
        return implode("\0", [$table, $attribute]);
    }

    /**
     * Encrypt multiple values. Null and empty values are passed through unchanged.
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
     * Decrypt multiple values. Null and empty values are passed through unchanged.
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
     * Check whether a plain text value matches an encrypted value, in constant time.
     * The value is decrypted internally. Any failure to decrypt returns false.
     *
     * @param string $plainText
     * @param string $encryptedValue
     * @param string|null $manualKey
     * @param string $context
     * @return bool
     */
    public static function verify(
        string $plainText,
        string $encryptedValue,
        ?string $manualKey = null,
        string $context = ''
    ): bool {
        try {
            return hash_equals(self::decrypt($encryptedValue, $manualKey, $context), $plainText);
        } catch (\Exception) {
            return false;
        }
    }

    /**
     * Check whether a stored payload uses the v1.0.x format.
     *
     * @param string $encoded
     * @return bool
     */
    public static function isLegacyPayload(string $encoded): bool
    {
        return !str_starts_with(trim($encoded), self::PAYLOAD_PREFIX);
    }

    /**
     * Check whether v1.0.x payloads and search hashes are still accepted.
     *
     * @return bool
     */
    public static function legacySupportEnabled(): bool
    {
        return (bool) config('db-encrypt.legacy_support', true);
    }

    /**
     * Check if OpenSSL extension is loaded and the cipher method is available.
     *
     * @return bool
     */
    public static function isSupported(): bool
    {
        return extension_loaded('openssl')
            && in_array(self::ENCRYPTION_METHOD, array_map('strtolower', openssl_get_cipher_methods()), true);
    }

    /**
     * Get information about the encryption configuration. Never includes key material.
     *
     * @return array
     */
    public static function getInfo(): array
    {
        return [
            'method' => self::ENCRYPTION_METHOD,
            'hash_algorithm' => 'hkdf-' . self::KDF_ALGORITHM,
            'search_hash_algorithm' => 'hmac-' . self::SEARCH_HASH_ALGORITHM,
            'iv_length' => self::IV_LENGTH,
            'supported' => self::isSupported(),
            'key_configured' => !empty(config('db-encrypt.key')),
            'previous_keys' => count(array_filter((array) config('db-encrypt.previous_keys', []))),
            'legacy_support' => self::legacySupportEnabled(),
        ];
    }
}
