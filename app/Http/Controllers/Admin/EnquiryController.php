<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Enquiry;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class EnquiryController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Admin/Enquiries', [
            'adminBreadcrumbs' => [
                ['label' => 'Dashboard', 'href' => '/admin/dashboard'],
                ['label' => 'Travel Enquiries'],
            ],
            'enquiries' => Enquiry::with('tourPackage:id,title')->latest()->paginate(20),
        ]);
    }

    public function destroy(Enquiry $enquiry): RedirectResponse
    {
        $enquiry->delete();

        return back()->with('flash', 'Enquiry removed.');
    }
}
