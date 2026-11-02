<?php

namespace App\Support;

use Illuminate\Support\Carbon;

/**
 * Pakistan wall-clock time for things people type and read (CRM
 * follow-ups and viewings), while everything is stored in UTC.
 * "11:00" typed by a dealer in Lahore means 11:00 PKT, i.e. 06:00 UTC.
 */
class LocalTime
{
    public static function zone(): string
    {
        return (string) config('meezan.timezone');
    }

    /** A date/time typed in local time → UTC, for storage. */
    public static function fromInput(Carbon|string $value): Carbon
    {
        return $value instanceof Carbon ? $value->copy()->utc() : Carbon::parse($value, self::zone())->utc();
    }

    /** Value for an <input type="datetime-local">. */
    public static function toInput(Carbon $time): string
    {
        return $time->copy()->setTimezone(self::zone())->format('Y-m-d\TH:i');
    }

    /** Start and end of the local "today", in UTC, for queries. @return array{0: Carbon, 1: Carbon} */
    public static function today(): array
    {
        $start = Carbon::now(self::zone())->startOfDay();

        return [$start->copy()->utc(), $start->copy()->endOfDay()->utc()];
    }
}
