<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\SavePromotionalPopupRequest;
use App\Models\PromotionalPopup;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class PromotionalPopupController extends Controller
{
    public function index(): Response
    {
        $promotionalPopup = PromotionalPopup::query()->latest('id')->first();

        return Inertia::render('Admin/PromotionalPopup', [
            'promotionalPopup' => $promotionalPopup ? [
                'id' => $promotionalPopup->id,
                'title' => $promotionalPopup->title,
                'cta_url' => $promotionalPopup->cta_url,
                'is_active' => $promotionalPopup->is_active,
                'image_url' => $promotionalPopup->image_path
                    ? Storage::disk('public')->url($promotionalPopup->image_path)
                    : null,
            ] : null,
        ]);
    }

    public function store(SavePromotionalPopupRequest $request): RedirectResponse
    {
        $popup = PromotionalPopup::query()->latest('id')->first();

        if ($request->boolean('is_active')
            && ! $request->boolean('remove_image')
            && ! $request->hasFile('image_upload')
            && ! $popup?->image_path) {
            return back()->withErrors([
                'image_upload' => 'An image is required before the Promotional Popup can be activated.',
            ])->withInput();
        }

        $oldImagePath = $popup?->image_path;
        $data = $request->safe()->except(['image_upload', 'remove_image']);

        if ($request->hasFile('image_upload')) {
            $data['image_path'] = $request->file('image_upload')->store('promotional-popups', 'public');
        } elseif ($request->boolean('remove_image')) {
            $data['image_path'] = null;
            $data['is_active'] = false;
        }

        DB::transaction(function () use ($popup, $data): void {
            if ($data['is_active'] ?? false) {
                PromotionalPopup::query()
                    ->when($popup, fn ($query) => $query->where('id', '!=', $popup->getKey()))
                    ->update(['is_active' => false]);
            }

            $popup ? $popup->update($data) : PromotionalPopup::create($data);
        });

        if (array_key_exists('image_path', $data) && $oldImagePath && $oldImagePath !== $data['image_path']) {
            Storage::disk('public')->delete($oldImagePath);
        }

        Cache::forget('active.promotional_popup');

        return back()->with('flash', 'Promotional Popup updated.');
    }
}
