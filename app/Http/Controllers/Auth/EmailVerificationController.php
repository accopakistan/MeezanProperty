<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\AuditLogger;
use Illuminate\Foundation\Auth\EmailVerificationRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class EmailVerificationController extends Controller
{
    public function notice(Request $request): View|RedirectResponse
    {
        return $request->user()->hasVerifiedEmail()
            ? redirect()->route($request->user()->role->homeRoute())
            : view('auth.verify-email');
    }

    /** The signed link from the email; the user must be signed in as that account. */
    public function verify(EmailVerificationRequest $request): RedirectResponse
    {
        if (! $request->user()->hasVerifiedEmail()) {
            $request->fulfill();
            AuditLogger::record('user.email_verified', $request->user());
        }

        return redirect()->route($request->user()->role->homeRoute())->with('status', 'Thank you, your email address is confirmed.');
    }

    public function send(Request $request): RedirectResponse
    {
        if ($request->user()->hasVerifiedEmail()) {
            return redirect()->route($request->user()->role->homeRoute());
        }

        $request->user()->sendEmailVerificationNotification();

        return back()->with('status', 'A new confirmation link has been sent to '.$request->user()->email.'.');
    }
}
