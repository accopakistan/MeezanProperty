<?php

namespace App\Http\Controllers;

use App\Enums\Role;
use App\Models\DealerProfile;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

/**
 * "Sell or list a property": routes private owners and dealers to the right
 * sign-up. Owners use the same listing tools as dealers but are labelled as
 * owners, are never shown as verified dealers and never receive buyer leads.
 */
class SellController extends Controller
{
    public function index(Request $request): View|RedirectResponse
    {
        return view('sell.index', ['user' => $request->user()]);
    }

    public function createOwner(): View
    {
        return view('sell.owner-register');
    }

    public function storeOwner(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'string', 'lowercase', 'email:rfc', 'max:190', 'unique:users,email'],
            'phone' => ['required', 'string', 'regex:/^[+0-9][0-9\s\-]{9,17}$/'],
            'password' => ['required', 'confirmed', Password::defaults()],
            'city' => ['required', Rule::in(config('meezan.cities'))],
            'terms' => ['accepted'],
        ], [
            'phone.regex' => 'Enter a valid phone number, e.g. 0300 1234567.',
            'terms.accepted' => 'Please accept the terms of use and privacy policy.',
        ]);

        $user = DB::transaction(function () use ($data) {
            $user = new User(['name' => $data['name'], 'email' => $data['email'], 'phone' => $data['phone'], 'password' => $data['password'], 'city' => $data['city']]);
            $user->role = Role::Dealer;
            $user->save();

            $profile = new DealerProfile([
                'agency_name' => $data['name'],
                'slug' => 'owner-'.Str::slug($data['name']).'-'.$user->id,
                'city' => $data['city'],
                'accepts_leads' => false,
            ]);
            $profile->business_type = 'owner';
            $profile->user_id = $user->id;
            $profile->save();

            AuditLogger::record('owner.registered', $profile, [], $user->id);

            return $user;
        });

        event(new Registered($user));
        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('dealer.properties.create')->with('status', 'Your account is ready. Add your property — it will be reviewed before it goes live.');
    }
}
