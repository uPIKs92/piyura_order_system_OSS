<?php

namespace App\Exceptions;

use Exception;

class InsufficientStockException extends Exception
{
    public function __construct(string $productName, int $available, int $required)
    {
        parent::__construct(
            "Stok {$productName} tidak cukup. Tersedia: {$available}, dibutuhkan: {$required}."
        );
    }
}
