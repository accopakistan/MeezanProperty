<?php

namespace App\Enums;

enum LeadStatus: string
{
    case New = 'new';
    case Assigned = 'assigned';
    case Contacted = 'contacted';
    case Qualified = 'qualified';
    case Closed = 'closed';
    case Lost = 'lost';
    case Unrouted = 'unrouted';

    public function label(): string
    {
        return match ($this) {
            self::New => 'New',
            self::Assigned => 'Assigned',
            self::Contacted => 'Contacted',
            self::Qualified => 'Qualified',
            self::Closed => 'Closed',
            self::Lost => 'Lost',
            self::Unrouted => 'Needs review',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::New => 'info',
            self::Assigned => 'brand',
            self::Contacted, self::Qualified => 'warning',
            self::Closed => 'success',
            self::Lost => 'muted',
            self::Unrouted => 'danger',
        };
    }
}
