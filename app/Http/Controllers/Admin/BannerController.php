<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Banner;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Inertia\Inertia;

class BannerController extends Controller
{
    public function index()
    {
        return Inertia::render('Admin/Banners', ['banners' => Banner::orderBy('sort_order')->get()]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'image' => ['required', 'image', 'max:4096'],
            'title' => ['nullable', 'string', 'max:255'],
            'subtitle' => ['nullable', 'string', 'max:255'],
            'cta_label' => ['nullable', 'string', 'max:100'],
            'cta_link' => ['nullable', 'string', 'max:255'],
        ]);

        $path = $request->file('image')->store('banners', 'public');

        Banner::create([
            'image_path' => $path,
            'title' => $data['title'] ?? null,
            'subtitle' => $data['subtitle'] ?? null,
            'cta_label' => $data['cta_label'] ?? null,
            'cta_link' => $data['cta_link'] ?? null,
            'sort_order' => Banner::max('sort_order') + 1,
        ]);

        Cache::forget('home.banners');

        return back()->with('flash', 'Banner added.');
    }

    public function destroy(Banner $banner)
    {
        $banner->delete();
        Cache::forget('home.banners');

        return back()->with('flash', 'Banner removed.');
    }
}
