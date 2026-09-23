<?php

namespace App\Http\Controllers;

use App\Models\City;
use App\Services\Discovery\DiscoveryLandingService;
use App\Support\Localization;
use Inertia\Inertia;
use Inertia\Response;

class CityController extends Controller
{
    public function show(City $city, DiscoveryLandingService $landing): Response
    {
        abort_unless($city->is_active, 404);

        $data = $landing->forCity($city, Localization::currentLocale());

        return Inertia::render('Static/City', [
            'city' => $data,
            'localizedSeo' => $data['seo'],
            'seo' => $data['seo'],
        ]);
    }
}
