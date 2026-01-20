<?php

namespace Wazza\DbEncrypt\Exceptions;

use InvalidArgumentException;

class InvalidAttributeException extends InvalidArgumentException
{
    /**
     * Create a new invalid attribute exception.
     *
     * @param string $message
     * @param int $code
     * @param \Throwable|null $previous
     */
    public function __construct(string $message = "Invalid attribute configuration", int $code = 0, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }

    /**
     * Create an exception for column conflicts.
     *
     * @param string $attribute
     * @param string $table
     * @return static
     */
    public static function columnConflict(string $attribute, string $table): static
    {
        return new static(
            "Cannot encrypt attribute '{$attribute}' because it already exists as a column in table '{$table}'. " .
            "Encrypted attributes must not exist as database columns."
        );
    }

    /**
     * Create an exception for undefined encrypted property.
     *
     * @param string $property
     * @return static
     */
    public static function undefinedProperty(string $property): static
    {
        return new static(
            "Property '{$property}' is not defined in the model's \$encryptedProperties array. " .
            "Only properties listed in \$encryptedProperties can be encrypted."
        );
    }

    /**
     * Create an exception for null input.
     *
     * @return static
     */
    public static function nullInput(): static
    {
        return new static('The value to encrypt or decrypt cannot be null.');
    }
}
