<?php

namespace App\Enums;

enum TransactionType: string
{
    case TopUp = 'top_up';
    case Credit = 'credit';
    case Hold = 'hold';
    case Release = 'release';
    case Usage = 'usage';
    case Refund = 'refund';
    case Adjustment = 'adjustment';
}
