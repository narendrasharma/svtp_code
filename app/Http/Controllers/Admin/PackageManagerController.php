<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StorePackageRequest;
use App\Models\City;
use App\Models\TourPackage;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Inertia\Inertia;

class PackageManagerController extends Controller
{
    public function index()
    {
        return Inertia::render('Admin/Packages/Index', [
            'packages' => TourPackage::with('city')->latest()->paginate(15),
        ]);
    }

    public function create()
    {
        return Inertia::render('Admin/Packages/Form', ['cities' => City::all(['id', 'name'])]);
    }

    public function store(StorePackageRequest $request)
    {
        $data = $request->validated();
        $data['slug'] = Str::slug($data['title']) . '-' . Str::random(4);

        TourPackage::create($data);
        Cache::forget('home.featured_packages');

        return redirect()->route('admin.packages.index')->with('flash', 'Package created.');
    }

    public function edit(TourPackage $package)
    {
        return Inertia::render('Admin/Packages/Form', [
            'package' => $package,
            'cities' => City::all(['id', 'name']),
        ]);
    }

    public function update(StorePackageRequest $request, TourPackage $package)
    {
        $package->update($request->validated());
        Cache::forget('home.featured_packages');

        return redirect()->route('admin.packages.index')->with('flash', 'Package updated.');
    }

    public function destroy(TourPackage $package)
    {
        $package->delete();
        Cache::forget('home.featured_packages');

        return back()->with('flash', 'Package removed.');
    }
}
