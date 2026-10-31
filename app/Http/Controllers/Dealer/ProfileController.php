<?php

namespace App\Http\Controllers\Dealer;

use App\Http\Controllers\Controller;
use App\Models\Area;
use App\Services\AuditLogger;
use App\Support\DealerCoverage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class ProfileController extends Controller
{
    public function edit(Request $request): View
    {
        $dealer = $request->user()->load('dealerProfile.areas', 'dealerProfile.cities');

        return view('dealer.profile', [
            'dealer' => $dealer,
            'profile' => $dealer->dealerProfile,
            'suggestions' => DealerCoverage::suggestions(),
            'selectedAreas' => old('areas') !== null
                ? Area::whereIn('id', array_map('intval', (array) old('areas')))->get(['id', 'name', 'city'])
                : $dealer->dealerProfile->areas,
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $profile = $request->user()->dealerProfile;
        $phone = ['nullable', 'string', 'regex:/^[+0-9][0-9\s\-]{9,17}$/'];

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'phone' => ['required', 'string', 'regex:/^[+0-9][0-9\s\-]{9,17}$/'],
            'agency_name' => ['required', 'string', 'max:150'],
            'office_address' => ['nullable', 'string', 'max:255'],
            'registration_number' => ['nullable', 'string', 'max:80'],
            'years_in_business' => ['nullable', 'integer', 'between:0,80'],
            'bio' => ['nullable', 'string', 'max:1500'],
            'public_phone' => $phone,
            'whatsapp_number' => $phone,
            'accepts_leads' => ['boolean'],
            'notify_account_email' => ['boolean'],
            ...DealerCoverage::rules($request, $profile->city),
        ], [
            'public_phone.regex' => 'Enter a valid phone number.',
            'whatsapp_number.regex' => 'Enter a valid WhatsApp number, including country code if outside Pakistan.',
        ]);

        $request->user()->update([
            'name' => $data['name'],
            'phone' => $data['phone'],
            // Absent from older forms: keep the current choice rather than switching emails off.
            'notify_account_email' => $request->has('notify_account_email') ? $request->boolean('notify_account_email') : $request->user()->notify_account_email,
        ]);

        $before = $profile->only(['agency_name', 'registration_number']);
        $profile->fill([
            'agency_name' => $data['agency_name'],
            'office_address' => $data['office_address'] ?? null,
            'registration_number' => $data['registration_number'] ?? null,
            'years_in_business' => $data['years_in_business'] ?? null,
            'bio' => $data['bio'] ?? null,
            'public_phone' => $data['public_phone'] ?? null,
            'whatsapp_number' => isset($data['whatsapp_number']) ? $this->normaliseWhatsApp($data['whatsapp_number']) : null,
            'accepts_leads' => $request->boolean('accepts_leads'),
        ]);
        $identityChanged = $profile->isDirty(['agency_name', 'registration_number']);
        $profile->save();
        DealerCoverage::save($profile, DealerCoverage::cities($request, $profile->city), $data['areas']);

        AuditLogger::record('dealer.profile_updated', $profile, $identityChanged ? ['before' => $before, 'after' => $profile->only(['agency_name', 'registration_number'])] : []);

        return back()->with('status', 'Profile updated.');
    }

    public function updatePassword(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        $request->user()->update(['password' => $data['password']]);

        return back()->with('status', 'Password changed.');
    }

    /**
     * Convert local numbers (03xx...) to international format for wa.me links.
     */
    protected function normaliseWhatsApp(string $number): string
    {
        $digits = preg_replace('/\D+/', '', $number);

        return str_starts_with($digits, '0') ? '92'.substr($digits, 1) : $digits;
    }
}
