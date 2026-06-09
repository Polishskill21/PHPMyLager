<?php

namespace App\Enums;

enum PurchaseOrderStatus: string
{
    case Open      = 'offen';
    case Ordered   = 'bestellt';
    case Delivered = 'geliefert';
    case Cancelled = 'storniert';

    public function toEnglish(): string
    {
        return match($this) {
            self::Open      => 'open',
            self::Ordered   => 'ordered',
            self::Delivered => 'delivered',
            self::Cancelled => 'cancelled',
        };
    }
}