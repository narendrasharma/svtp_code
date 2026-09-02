<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Enquiry;
use Inertia\Inertia;
use Inertia\Response;

class EnquiryController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Admin/Enquiries', [
            'enquiries' => Enquiry::with('tourPackage:id,title')->latest()->paginate(20),
        ]);
    }
}
