<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class ProfileController extends Controller
{
    public function edit(Request $request): View
    {
        return view('account.profile', ['user' => $request->user()]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'phone' => ['nullable', 'string', 'regex:/^[+0-9][0-9\s\-]{9,17}$/'],
            'city' => ['nullable', Rule::in(config('meezan.cities'))],
        ], ['phone.regex' => 'Enter a valid phone number, e.g. 0300 1234567.']);

        $request->user()->update($data);

        return back()->with('status', 'Profile updated.');
    }

    public function settings(Request $request): View
    {
        return view('account.settings', ['user' => $request->user()]);
    }

    public function updateNotifications(Request $request): RedirectResponse
    {
        $request->user()->update([
            'notify_in_app' => $request->boolean('notify_in_app'),
            'notify_email' => $request->boolean('notify_email'),
            'notify_whatsapp' => $request->boolean('notify_whatsapp'),
        ]);

        return back()->with('status', 'Notification preferences saved.');
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
}
