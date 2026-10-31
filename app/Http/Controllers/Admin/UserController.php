<?php

namespace App\Http\Controllers\Admin;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class UserController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'role' => ['nullable', Rule::enum(Role::class)],
            'status' => ['nullable', Rule::in(['active', 'suspended'])],
        ]);

        $users = User::query()
            ->with('dealerProfile:id,user_id,agency_name,verification_status')
            ->when($filters['q'] ?? null, fn ($q, $term) => $q->where(fn ($w) => $w->where('name', 'like', "%{$term}%")->orWhere('email', 'like', "%{$term}%")))
            ->when($filters['role'] ?? null, fn ($q, $r) => $q->where('role', $r))
            ->when($filters['status'] ?? null, fn ($q, $s) => $q->where('status', $s))
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return view('admin.users', compact('users', 'filters'));
    }

    public function updateStatus(Request $request, User $user): RedirectResponse
    {
        abort_if($user->is($request->user()), 422, 'You cannot change your own account status.');

        $data = $request->validate(['status' => ['required', Rule::in(['active', 'suspended'])]]);
        $user->status = $data['status'];
        $user->save();

        AuditLogger::record('user.'.($data['status'] === 'active' ? 'reactivated' : 'suspended'), $user);

        return back()->with('status', $user->name.' is now '.$data['status'].'.');
    }
}
