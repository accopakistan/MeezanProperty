@php
    $success = session('status');
    $error = session('error');
@endphp
@if ($success || $error)
    <div {{ $attributes }}>
        <div role="status" @class([
            'flex items-start gap-3 rounded-xl border px-4 py-3 text-sm font-medium',
            'border-emerald-200 bg-emerald-50 text-emerald-800' => $success,
            'border-red-200 bg-red-50 text-red-800' => $error,
        ])>
            <x-icon :name="$success ? 'check' : 'alert'" class="mt-0.5 size-4" />
            <p class="flex-1">{{ $success ?? $error }}</p>
        </div>
    </div>
@endif
