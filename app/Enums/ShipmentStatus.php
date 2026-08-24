<?php

namespace App\Enums;

enum ShipmentStatus: string
{
    case Pending = 'pending';
    case LabelCreated = 'label_created';
    case Shipped = 'shipped';
    case InTransit = 'in_transit';
    case Delivered = 'delivered';
    case Exception = 'exception';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::LabelCreated => 'Label created',
            self::Shipped => 'Shipped',
            self::InTransit => 'In transit',
            self::Delivered => 'Delivered',
            self::Exception => 'Exception',
            self::Cancelled => 'Cancelled',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Pending, self::LabelCreated => 'gray',
            self::Shipped, self::InTransit => 'info',
            self::Delivered => 'success',
            self::Exception => 'danger',
            self::Cancelled => 'gray',
        };
    }
}
