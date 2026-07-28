<?php

namespace App\Exceptions;

use Exception;

class OrderVersionMismatchException extends Exception
{
    public function __construct()
    {
        parent::__construct('Order telah diubah oleh pengguna lain. Muat ulang.');
    }
}
