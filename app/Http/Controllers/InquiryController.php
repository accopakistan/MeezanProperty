<?php

namespace App\Http\Controllers;

use App\Models\Inquiry;
use App\Models\Property;
use App\Models\User;
use App\Notifications\NewInquiryReceived;
use App\Rules\PhoneNumber;
use App\Services\Leads\LeadRouter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class InquiryController extends Controller
{
    public function store(Request $request, Property $property, LeadRouter $router): RedirectResponse
    {
        abort_unless($property->isPublic(), 404);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email:rfc', 'max:190'],
            'phone' => ['required', 'string', new PhoneNumber],
            'message' => ['required', 'string', 'min:10', 'max:2000'],
            'preferred_contact' => ['required', Rule::in(['call', 'whatsapp', 'email'])],
            'consent' => ['accepted'],
            'website' => ['prohibited'], // honeypot
        ], [
            'phone.regex' => 'Enter a valid phone number, e.g. 0300 1234567.',
            'consent.accepted' => 'Please confirm that we may share your details with the listing dealer.',
        ]);

        $windowStart = now()->subMinutes((int) config('meezan.leads.dedupe_window_minutes', 30));
        $duplicate = Inquiry::where('property_id', $property->id)
            ->where('email', $data['email'])
            ->where('created_at', '>=', $windowStart)
            ->exists();

        if ($duplicate) {
            return back()->with('status', 'We already received your inquiry for this property. The dealer will be in touch.');
        }

        $inquiry = new Inquiry([
            'name' => $data['name'],
            'email' => $data['email'],
            'phone' => $data['phone'],
            'message' => $data['message'],
            'preferred_contact' => $data['preferred_contact'],
            'consent_to_share' => true,
        ]);
        $inquiry->property_id = $property->id;
        $inquiry->user_id = $request->user()?->id;
        $inquiry->recipient_id = $property->user_id;
        $inquiry->source = 'website';
        $inquiry->ip_hash = hash('sha256', $request->ip().config('app.key'));
        $inquiry->save();

        $router->routeInquiry($inquiry);

        User::find($inquiry->recipient_id)?->notify(new NewInquiryReceived($inquiry));

        return back()->with('status', 'Thank you — your inquiry has been sent to the dealer.');
    }
}
