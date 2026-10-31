<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\View\View;

/**
 * Subscriptions and payments are not implemented: there are no plan,
 * subscription or payment tables yet and no payment provider is configured.
 * These pages say so rather than showing invented figures.
 */
class BillingController extends Controller
{
    public function subscriptions(): View
    {
        return view('admin.billing', ['page' => 'subscriptions']);
    }

    public function payments(): View
    {
        return view('admin.billing', ['page' => 'payments']);
    }
}
