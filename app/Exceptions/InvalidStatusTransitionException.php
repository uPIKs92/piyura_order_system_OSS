<?php

namespace App\Exceptions;

use Exception;

class InvalidStatusTransitionException extends Exception
{
    public function __construct(string $from, string $to)
    {
        parent::__construct("Invalid status transition from {$from} to {$to}");
    }
}
