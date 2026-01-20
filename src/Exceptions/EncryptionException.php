<?php

namespace Wazza\DbEncrypt\Exceptions;

use RuntimeException;

class EncryptionException extends RuntimeException
{
    /**
     * Create a new encryption exception.
     *
     * @param string $message
     * @param int $code
     * @param \Throwable|null $previous
     */
    public function __construct(string $message = "Encryption operation failed", int $code = 0, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }

    /**
     * Create an exception for missing encryption key.
     *
     * @return static
     */
    public static function missingKey(): static
    {
        return new static('Encryption key is not set. Please set DB_ENCRYPT_KEY in your .env file or publish the config and set it there.');
    }

    /**
     * Create an exception for encryption failure.
     *
     * @return static
     */
    public static function encryptionFailed(): static
    {
        return new static('Failed to encrypt the data. Please check your encryption key and OpenSSL configuration.');
    }
}
