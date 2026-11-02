<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Package;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Packages are created, edited and switched on or off here; never deleted,
 * so past subscriptions and invoices keep their package. Changes apply to
 * new subscriptions only: existing ones keep their snapshot.
 */
class PackageController extends Controller
{
    public function index(): View
    {
        return view('admin.packages.index', [
            'packages' => Package::ordered()->withCount(['subscriptions as running_count' => fn ($q) => $q->inForce()])->get(),
            'hasDefault' => Package::defaultPlan() !== null,
        ]);
    }

    public function create(): View
    {
        return view('admin.packages.form', ['package' => new Package(['currency' => config('meezan.billing.currency'), 'billing_interval' => 'month', 'is_active' => true, 'price' => 0])]);
    }

    public function store(Request $request): RedirectResponse
    {
        $package = DB::transaction(function () use ($request) {
            $package = new Package($this->validated($request));
            $package->save();
            $this->keepSingle($package);

            return $package;
        });

        AuditLogger::record('package.created', $package, ['package' => $package->snapshot()]);

        return redirect()->route('admin.packages.index')->with('status', $package->name.' created.');
    }

    public function edit(Package $package): View
    {
        return view('admin.packages.form', ['package' => $package]);
    }

    public function update(Request $request, Package $package): RedirectResponse
    {
        $data = $this->validated($request, $package);

        DB::transaction(function () use ($package, $data) {
            $before = $package->only(array_keys($data));
            $package->fill($data)->save();
            $this->keepSingle($package);

            $changes = collect($package->getChanges())->except('updated_at')->keys();
            if ($changes->isNotEmpty()) {
                AuditLogger::record('package.updated', $package, [
                    'from' => collect($before)->only($changes)->all(),
                    'to' => $package->only($changes->all()),
                ]);
            }
        });

        return redirect()->route('admin.packages.index')->with('status', $package->name.' saved. Existing subscriptions keep the terms they started with.');
    }

    public function toggle(Package $package): RedirectResponse
    {
        $package->is_active = ! $package->is_active;
        $package->save();
        AuditLogger::record($package->is_active ? 'package.activated' : 'package.deactivated', $package);

        return back()->with('status', $package->name.($package->is_active ? ' is available to dealers.' : ' is hidden from dealers. Current subscribers keep it until it ends.'));
    }

    protected function validated(Request $request, ?Package $package = null): array
    {
        $limit = ['nullable', 'integer', 'min:0', 'max:100000'];
        $data = $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'slug' => ['nullable', 'alpha_dash', 'max:80', Rule::unique('packages', 'slug')->ignore($package?->id)],
            'description' => ['nullable', 'string', 'max:500'],
            'price' => ['required', 'numeric', 'min:0', 'max:99999999'],
            'currency' => ['required', 'string', 'size:3', 'regex:/^[A-Z]{3}$/'],
            'billing_interval' => ['required', Rule::in(array_keys(Package::INTERVALS))],
            'trial_days' => ['nullable', 'integer', 'min:0', 'max:365'],
            'listing_limit' => $limit,
            'lead_limit' => $limit,
            'featured_limit' => $limit,
            'team_member_limit' => $limit,
            'ai_request_limit' => $limit,
            'features' => ['nullable', 'array'],
            'features.*' => [Rule::in(array_keys(config('meezan.billing.features')))],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:1000'],
        ], ['currency.regex' => 'Use a three-letter currency code such as PKR.']);

        $data['trial_days'] = (int) ($data['trial_days'] ?? 0);
        $data['sort_order'] = (int) ($data['sort_order'] ?? 0);
        $data['features'] = array_values($data['features'] ?? []);
        foreach (['is_active', 'is_default', 'is_recommended', 'is_demo'] as $flag) {
            $data[$flag] = $request->boolean($flag);
        }

        // The default plan is what every dealer without a subscription gets, so it must be free.
        if ($data['is_default'] && (float) $data['price'] > 0) {
            throw ValidationException::withMessages(['is_default' => 'Only a free package (price 0) can be the default plan.']);
        }

        return $data;
    }

    /** Only one default plan and one recommended package at a time. */
    protected function keepSingle(Package $package): void
    {
        foreach (['is_default', 'is_recommended'] as $flag) {
            if ($package->{$flag}) {
                Package::whereKeyNot($package->id)->where($flag, true)->get()->each(function (Package $other) use ($flag) {
                    $other->{$flag} = false;
                    $other->save();
                    AuditLogger::record('package.updated', $other, ['from' => [$flag => true], 'to' => [$flag => false]]);
                });
            }
        }
    }
}
