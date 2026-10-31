<label class="flex items-center gap-2.5 text-sm text-ink">
    <input type="checkbox" name="{{ $name }}" value="{{ $value }}" class="checkbox" @checked($checked)>
    <span class="flex-1">{{ $label }}</span>
    <span class="rounded bg-page px-1.5 text-xs text-muted tabular-nums">{{ number_format($count) }}</span>
</label>
