<?php

namespace App\Enums;

enum PurchaseOrderStatus: string
{
    case Open      = 'offen';
    case Ordered   = 'bestellt';
    case Delivered = 'geliefert';
    case Cancelled = 'storniert';
}