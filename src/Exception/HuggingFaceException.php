<?php

namespace App\Exception;

use RuntimeException;

class HuggingFaceException extends RuntimeException
{
    private array $details;

    public function __construct(string $message, array $details = [], int $code = 0, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
        $this->details = $details;
    }

    public function getDetails(): array
    {
        return $this->details;
    }
}
