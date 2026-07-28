<?php

namespace App\Enums;

enum StockMovementType: string
{
    case Sale = 'sale';
    case Return = 'return';
    case Restock = 'restock';
    case Receive = 'receive';
    case Adjustment = 'adjustment';
    case CancelRestore = 'cancel_restore';
}
