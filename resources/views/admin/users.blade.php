<x-layouts.dashboard title="Users" section="admin" heading="Users" subheading="Buyers, dealers and administrators.">
    <form method="GET" class="card mb-5 grid gap-3 p-4 sm:grid-cols-[1fr_auto_auto_auto]">
        <label class="sr-only" for="q">Search</label>
        <input id="q" name="q" value="{{ $filters['q'] ?? '' }}" class="input" placeholder="Search name or email">
        <select name="role" class="input" aria-label="Role">
            <option value="">All roles</option>
            @foreach (\App\Enums\Role::cases() as $role)
                <option value="{{ $role->value }}" @selected(($filters['role'] ?? '') === $role->value)>{{ $role->label() }}</option>
            @endforeach
        </select>
        <select name="status" class="input" aria-label="Status">
            <option value="">Any status</option>
            <option value="active" @selected(($filters['status'] ?? '') === 'active')>Active</option>
            <option value="suspended" @selected(($filters['status'] ?? '') === 'suspended')>Suspended</option>
        </select>
        <button class="btn btn-primary">Filter</button>
    </form>

    @if ($users->isEmpty())
        <x-empty-state icon="users" title="No users match these filters" />
    @else
        <div class="card overflow-hidden">
            <div class="overflow-x-auto">
                <table class="table">
                    <thead><tr><th>Name</th><th>Role</th><th>Phone</th><th>Status</th><th>Joined</th><th>Last sign-in</th><th class="sr-only">Actions</th></tr></thead>
                    <tbody>
                        @foreach ($users as $user)
                            <tr>
                                <td>
                                    <p class="font-semibold">{{ $user->name }}</p>
                                    <p class="text-xs text-muted">{{ $user->email }}</p>
                                </td>
                                <td>
                                    <span class="badge bg-brand-50 text-brand-700">{{ $user->role->label() }}</span>
                                    @if ($user->dealerProfile)
                                        <a href="{{ route('admin.dealers.show', $user->dealerProfile) }}" class="mt-1 block text-xs text-muted hover:text-brand-700">{{ $user->dealerProfile->agency_name }}</a>
                                    @endif
                                </td>
                                <td class="text-muted">{{ $user->phone ?? '—' }}</td>
                                <td><x-status-badge :tone="$user->status === 'active' ? 'success' : 'danger'" :label="ucfirst($user->status)" /></td>
                                <td class="whitespace-nowrap text-muted">{{ $user->created_at->format('j M Y') }}</td>
                                <td class="whitespace-nowrap text-muted">{{ $user->last_login_at?->diffForHumans() ?? '—' }}</td>
                                <td class="text-right">
                                    @unless ($user->is(auth()->user()))
                                        <form method="POST" action="{{ route('admin.users.status', $user) }}" @if ($user->status === 'active') data-confirm="Suspend {{ $user->name }}? They will be signed out and unable to sign in." @endif>
                                            @csrf @method('PATCH')
                                            <input type="hidden" name="status" value="{{ $user->status === 'active' ? 'suspended' : 'active' }}">
                                            <button @class(['btn btn-sm', 'btn-ghost text-red-600' => $user->status === 'active', 'btn-secondary' => $user->status !== 'active'])>{{ $user->status === 'active' ? 'Suspend' : 'Reactivate' }}</button>
                                        </form>
                                    @endunless
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
        <div class="mt-6">{{ $users->links() }}</div>
    @endif
</x-layouts.dashboard>
