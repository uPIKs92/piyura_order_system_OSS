<?php

namespace App\Exceptions;

use Exception;

class ExpiredStockException extends Exception
{
    public function __construct(
        public readonly string $productName,
        public readonly int $unexpiredAvailable,
        public readonly int $expiredQty,
        public readonly int $required,
    ) {
        parent::__construct(
            "Stok {$productName} tidak cukup (stok belum kedaluwarsa: {$unexpiredAvailable}, dibutuhkan: {$required}).".
            " Stok kedaluwarsa: {$expiredQty} — lakukan write-off atau restock."
        );
    }
}
