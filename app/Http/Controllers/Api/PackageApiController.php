<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\PackageResource;
use App\Models\TourPackage;
use Illuminate\Http\Request;

class PackageApiController extends Controller
{
    public function index(Request $request)
    {
        $packages = TourPackage::active()->with('city')
            ->when($request->city, fn ($q) => $q->whereHas('city', fn ($c) => $c->where('slug', $request->city)))
            ->paginate(10);

        return PackageResource::collection($packages);
    }

    public function show(TourPackage $package)
    {
        return new PackageResource($package->load('city', 'approvedReviews.user'));
    }
}
