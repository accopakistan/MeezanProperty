<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Models\Conversation;
use App\Services\WhatsApp\AccountLinker;
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
        $user = $request->user();

        return view('account.settings', [
            'user' => $user,
            'linkCode' => app(AccountLinker::class)->pendingCode($user),
            'linkedChats' => Conversation::where('channel', 'whatsapp')->where('user_id', $user->id)->count(),
        ]);
    }

    /** One-time code the buyer sends from WhatsApp ("LINK 123456") to link that chat to this account. */
    public function whatsappLinkCode(Request $request, AccountLinker $linker): RedirectResponse
    {
        $linker->generate($request->user());

        return back()->with('status', 'Send the code shown below from WhatsApp within '.AccountLinker::TTL_MINUTES.' minutes.');
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
