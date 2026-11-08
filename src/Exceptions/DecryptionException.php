<?php

namespace Wazza\DbEncrypt\Exceptions;

use RuntimeException;

class DecryptionException extends RuntimeException
{
    /**
     * Create a new decryption exception.
     *
     * @param string $message
     * @param int $code
     * @param \Throwable|null $previous
     */
    public function __construct(string $message = "Decryption operation failed", int $code = 0, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }

    /**
     * Create an exception for invalid base64 input.
     *
     * @return static
     */
    public static function invalidBase64(): static
    {
        return new static('The encrypted data is not valid base64. The data may be corrupted.');
    }

    /**
     * Create an exception for decryption failure.
     *
     * @return static
     */
    public static function decryptionFailed(): static
    {
        return new static('Failed to decrypt the data. The key may be incorrect or the data may be corrupted or tampered with.');
    }

    /**
     * Create an exception for a payload too short to contain its required parts.
     *
     * @return static
     */
    public static function invalidPayload(): static
    {
        return new static('The encrypted data is malformed. The data may be corrupted.');
    }

    /**
     * Create an exception for a legacy payload when legacy support is turned off.
     *
     * @return static
     */
    public static function legacyDisabled(): static
    {
        return new static('The encrypted data uses the legacy v1.0 format and DB_ENCRYPT_LEGACY_SUPPORT is off. Enable it and run db-encrypt:re-encrypt.');
    }

    /**
     * Create an exception for a model whose attributes could not be loaded.
     *
     * @param string $modelClass
     * @param \Throwable $previous
     * @return static
     */
    public static function attributeFailed(string $modelClass, \Throwable $previous): static
    {
        return new static("Failed to decrypt encrypted attributes for model {$modelClass}. Please check the logs for details.", 0, $previous);
    }
}
