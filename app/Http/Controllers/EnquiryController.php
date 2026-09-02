<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreEnquiryRequest;
use App\Models\Enquiry;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class EnquiryController extends Controller
{
    public function create(): Response
    {
        return Inertia::render('Static/Contact');
    }

    public function store(StoreEnquiryRequest $request): RedirectResponse
    {
        Enquiry::create($request->safe()->except('security_answer'));

        $request->session()->forget('enquiry_math_answer');

        return back()->with('flash', 'Thank you. Your enquiry has been received.');
    }
}
