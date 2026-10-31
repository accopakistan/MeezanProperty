<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AuditLogController extends Controller
{
    public function __invoke(Request $request): View
    {
        $filters = $request->validate([
            'action' => ['nullable', 'string', 'max:80'],
        ]);

        return view('admin.audit-logs', [
            'logs' => AuditLog::with('user:id,name,role')
                ->when($filters['action'] ?? null, fn ($q, $a) => $q->where('action', 'like', $a.'%'))
                ->latest('created_at')
                ->latest('id')
                ->paginate(40)
                ->withQueryString(),
            'actions' => AuditLog::query()->distinct()->orderBy('action')->pluck('action')
                ->map(fn ($a) => explode('.', $a)[0])->unique()->values(),
            'filters' => $filters,
        ]);
    }
}
