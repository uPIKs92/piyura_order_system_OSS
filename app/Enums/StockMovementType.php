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
    case DeleteRestore = 'delete_restore';
    case WriteOff = 'write_off';
}
