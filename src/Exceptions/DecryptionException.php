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
        return new static('Failed to decrypt the data. The key may be incorrect or the data may be corrupted.');
    }
}
