<?php

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;

class AuditLogger
{
    /**
     * @param  array<string, mixed>  $properties
     */
    public static function record(string $action, ?Model $subject = null, array $properties = [], ?int $userId = null): AuditLog
    {
        $log = new AuditLog([
            'action' => $action,
            'properties' => $properties ?: null,
            'ip_address' => app()->runningInConsole() ? null : request()->ip(),
        ]);

        $log->user_id = $userId ?? auth()->id();

        if ($subject) {
            $log->auditable()->associate($subject);
        }

        $log->save();

        return $log;
    }
}
