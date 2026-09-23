<?php

namespace App\Http\Controllers;

use App\Services\HomepageService;
use Inertia\Inertia;
use Inertia\Response;

class HomeController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Home', [
            'homepage' => app(HomepageService::class)->compose(),
        ]);
    }
}
