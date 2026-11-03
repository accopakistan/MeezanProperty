<x-layouts.dashboard title="AI Connectors" section="admin" heading="Connectors" subheading="External services the agents can use. “Configured” means settings are present; only a successful check marks a connector “Verified”. Keys are set in the server environment and are never shown here.">
    @include('admin.ai.partials.nav')

    <div class="card relative overflow-x-auto">
        <table class="table table-compact min-w-[720px]">
            <thead><tr><th scope="col">Connector</th><th scope="col">Status</th><th scope="col">Details</th><th scope="col">Last check</th><th scope="col"><span class="sr-only">Check</span></th></tr></thead>
            <tbody>
                @foreach ($connectors as $key => $c)
                    <tr>
                        <td class="font-semibold">{{ $c['label'] }}</td>
                        <td>@include('admin.ai.partials.status', ['status' => $c['status']])</td>
                        <td class="max-w-md text-[13px] text-muted">{{ $c['detail'] }}</td>
                        <td class="text-[13px] whitespace-nowrap">{{ $c['checked_at'] ?? '—' }}</td>
                        <td>@if ($c['checkable'] && $c['status'] !== 'not_configured')<form method="POST" action="{{ route('admin.ai.connectors.check', $key) }}">@csrf<button class="btn btn-secondary btn-sm">Check now</button></form>@endif</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</x-layouts.dashboard>
