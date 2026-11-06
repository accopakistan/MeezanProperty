<?php

namespace App\Http\Controllers\Auth;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Models\Area;
use App\Models\DealerProfile;
use App\Models\User;
use App\Rules\PhoneNumber;
use App\Services\AuditLogger;
use App\Support\DealerCoverage;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class DealerRegistrationController extends Controller
{
    public function create(): View
    {
        return view('auth.dealer-register', [
            'suggestions' => DealerCoverage::suggestions(),
            'selectedAreas' => Area::whereIn('id', array_map('intval', (array) old('areas', [])))->get(['id', 'name', 'city']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'string', 'lowercase', 'email:rfc', 'max:190', 'unique:users,email'],
            'phone' => ['required', 'string', new PhoneNumber],
            'password' => ['required', 'confirmed', Password::defaults()],
            'business_type' => ['required', Rule::in(['individual', 'agency'])],
            'agency_name' => ['required', 'string', 'max:150'],
            'city' => ['required', Rule::in(config('meezan.cities'))],
            ...DealerCoverage::rules($request, (string) $request->input('city')),
            'office_address' => ['nullable', 'string', 'max:255'],
            'registration_number' => ['nullable', 'string', 'max:80'],
            'years_in_business' => ['nullable', 'integer', 'between:0,80'],
            'terms' => ['accepted'],
        ], [
            'phone.regex' => 'Enter a valid phone number, e.g. 0300 1234567.',
            'areas.required' => 'Select at least one area where you operate.',
            'terms.accepted' => 'Please accept the terms of use and privacy policy.',
        ]);

        $cities = DealerCoverage::cities($request, $data['city']);

        $user = DB::transaction(function () use ($data, $cities) {
            $user = new User([
                'name' => $data['name'],
                'email' => $data['email'],
                'phone' => $data['phone'],
                'password' => $data['password'],
                'city' => $data['city'],
            ]);
            $user->role = Role::Dealer;
            $user->save();

            $profile = new DealerProfile([
                'agency_name' => $data['agency_name'],
                'slug' => $this->uniqueSlug($data['agency_name']),
                'city' => $data['city'],
                'office_address' => $data['office_address'] ?? null,
                'registration_number' => $data['registration_number'] ?? null,
                'years_in_business' => $data['years_in_business'] ?? null,
                'public_phone' => null,
                'whatsapp_number' => null,
            ]);
            $profile->business_type = $data['business_type'];
            $profile->user()->associate($user);
            $profile->save();
            DealerCoverage::save($profile, $cities, $data['areas']);

            AuditLogger::record('dealer.registered', $profile, ['agency' => $profile->agency_name], $user->id);

            return $user;
        });

        event(new Registered($user));
        Auth::login($user);
        request()->session()->regenerate();

        return redirect()->route('dealer.dashboard')
            ->with('status', 'Your dealer account is created. Our team will review your agency details before your profile is marked verified.');
    }

    protected function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'agency';
        $slug = $base;
        $i = 2;

        while (DealerProfile::where('slug', $slug)->exists()) {
            $slug = $base.'-'.$i++;
        }

        return $slug;
    }
}
