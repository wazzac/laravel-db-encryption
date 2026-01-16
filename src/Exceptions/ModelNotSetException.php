<?php

namespace Wazza\DbEncrypt\Exceptions;

use RuntimeException;

class ModelNotSetException extends RuntimeException
{
    /**
     * Create a new model not set exception.
     *
     * @param string $message
     * @param int $code
     * @param \Throwable|null $previous
     */
    public function __construct(string $message = "Model is not set", int $code = 0, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }

    /**
     * Create an exception for missing model.
     *
     * @return static
     */
    public static function required(): static
    {
        return new static('Model is not set. Please set the model using the setModel() method before performing operations.');
    }
}
