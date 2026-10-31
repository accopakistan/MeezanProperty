<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\View\View;

class InquiryController extends Controller
{
    public function __invoke(Request $request): View
    {
        return view('account.inquiries', [
            'inquiries' => $request->user()->inquiries()
                ->with(['property' => fn ($q) => $q->with('coverImage', 'area:id,name')])
                ->latest()
                ->paginate(15),
        ]);
    }
}
