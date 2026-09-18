<?php

namespace App\Http\Controllers\Vendor;

use App\Http\Controllers\Controller;
use App\Http\Requests\Vendor\UpdateVendorProfileRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class VendorProfileController extends Controller
{
    public function show(Request $request): Response
    {
        $user = $request->user();
        $profile = $user->vendorProfile()->firstOrFail();

        return Inertia::render('Vendor/Profile', [
            'profile' => $profile,
        ]);
    }

    public function edit(Request $request): Response
    {
        $user = $request->user();
        $profile = $user->vendorProfile()->firstOrFail();

        return Inertia::render('Vendor/ProfileEdit', [
            'profile' => $profile,
        ]);
    }

    public function update(UpdateVendorProfileRequest $request): RedirectResponse
    {
        $user = $request->user();
        $profile = $user->vendorProfile()->firstOrFail();

        $validated = $request->validated();

        // Only allow safe fields — never role, verification status, plan,
        // approval state or verified badge.
        $profile->update([
            'business_name' => $validated['business_name'],
            'phone' => $validated['phone'],
            'email' => $validated['email'],
            'address' => $validated['address'] ?? $profile->address,
            'city' => $validated['city'] ?? $profile->city,
            'state' => $validated['state'] ?? $profile->state,
            'country_code' => strtoupper($validated['country_code']),
            'postcode' => $validated['postcode'] ?? $profile->postcode,
            'website' => $validated['website'] ?? null,
            'business_description' => $validated['business_description'] ?? null,
            'public_description' => $validated['public_description'] ?? null,
            'public_phone' => $validated['public_phone'] ?? null,
            'public_email' => $validated['public_email'] ?? null,
            'social_links' => $validated['social_links'] ?? null,
            'storefront_enabled' => (bool) ($validated['storefront_enabled'] ?? true),
        ]);

        if ($request->hasFile('logo_upload')) {
            $this->deleteStoredFile($profile->logo_path);
            $profile->update(['logo_path' => $request->file('logo_upload')->store('vendors/logos', 'public')]);
        } elseif ($request->boolean('remove_logo')) {
            $this->deleteStoredFile($profile->logo_path);
            $profile->update(['logo_path' => null]);
        }

        if ($request->hasFile('cover_upload')) {
            $this->deleteStoredFile($profile->cover_path);
            $profile->update(['cover_path' => $request->file('cover_upload')->store('vendors/covers', 'public')]);
        } elseif ($request->boolean('remove_cover')) {
            $this->deleteStoredFile($profile->cover_path);
            $profile->update(['cover_path' => null]);
        }

        return redirect()->route('vendor.profile.show')->with('success', 'Profile updated.');
    }

    protected function deleteStoredFile(?string $path): void
    {
        if ($path !== null && $path !== '') {
            Storage::disk('public')->delete($path);
        }
    }
}
