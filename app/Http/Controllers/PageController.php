<?php

namespace App\Http\Controllers;

use App\Models\Inquiry;
use App\Models\User;
use App\Notifications\ContactMessageReceived;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;
use Illuminate\View\View;

class PageController extends Controller
{
    public function about(): View
    {
        return view('pages.about');
    }

    public function howItWorks(): View
    {
        return view('pages.how-it-works');
    }

    public function contact(): View
    {
        return view('pages.contact', ['contact' => config('meezan.contact')]);
    }

    public function sendContact(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email:rfc', 'max:190'],
            'phone' => ['required', 'string', 'regex:/^[+0-9][0-9\s\-]{9,17}$/'],
            'message' => ['required', 'string', 'min:10', 'max:2000'],
            'website' => ['prohibited'],
        ], ['phone.regex' => 'Enter a valid phone number, e.g. 0300 1234567.']);

        $inquiry = new Inquiry([
            'name' => $data['name'],
            'email' => $data['email'],
            'phone' => $data['phone'],
            'message' => $data['message'],
            'preferred_contact' => 'email',
            'consent_to_share' => false,
        ]);
        $inquiry->user_id = $request->user()?->id;
        $inquiry->source = 'contact';
        $inquiry->ip_hash = hash('sha256', $request->ip().config('app.key'));
        $inquiry->save();

        $contactEmail = config('meezan.contact.email');
        $contactEmail
            ? Notification::route('mail', $contactEmail)->notify(new ContactMessageReceived($inquiry))
            : Notification::send(User::activeAdmins(), new ContactMessageReceived($inquiry));

        return back()->with('status', 'Thank you — our team will reply to your message by email.');
    }

    public function terms(): View
    {
        return view('pages.legal', ['page' => 'terms']);
    }

    public function privacy(): View
    {
        return view('pages.legal', ['page' => 'privacy']);
    }
}
