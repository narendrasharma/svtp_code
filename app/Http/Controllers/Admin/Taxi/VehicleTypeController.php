<?php

namespace App\Http\Controllers\Admin\Taxi;

use App\Http\Controllers\Controller;
use App\Models\VehicleType;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Global vehicle types (12A.1). Admin-managed catalogue; vendors pick
 * from active types. No per-vendor custom types in this phase.
 */
class VehicleTypeController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Admin/Taxi/VehicleTypes/Index', [
            'types' => VehicleType::orderBy('sort_order')->orderBy('name')->paginate(25),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validated($request);

        if ($request->hasFile('image_upload')) {
            $validated['image_path'] = $request->file('image_upload')->store('taxi/vehicle-types', 'public');
        }

        unset($validated['image_upload'], $validated['remove_image']);

        VehicleType::create([
            ...$validated,
            'slug' => $validated['slug'] ?? Str::slug($validated['name']),
            'is_active' => $validated['is_active'] ?? true,
        ]);

        return back()->with('flash', 'Vehicle type created.');
    }

    public function update(Request $request, VehicleType $vehicleType): RedirectResponse
    {
        $validated = $this->validated($request, $vehicleType);
        $oldImage = $vehicleType->image_path;

        if ($request->hasFile('image_upload')) {
            $validated['image_path'] = $request->file('image_upload')->store('taxi/vehicle-types', 'public');
        } elseif ($request->boolean('remove_image')) {
            $validated['image_path'] = null;
        }

        unset($validated['image_upload'], $validated['remove_image']);

        $vehicleType->update($validated);

        if (array_key_exists('image_path', $validated) && $oldImage !== $validated['image_path']) {
            $this->deleteManagedImage($oldImage);
        }

        return back()->with('flash', 'Vehicle type updated.');
    }

    public function destroy(VehicleType $vehicleType): RedirectResponse
    {
        if ($vehicleType->vehicles()->exists()) {
            return back()->withErrors(['type' => 'Vehicle type is in use and cannot be deleted. Deactivate it instead.']);
        }

        $vehicleType->delete();

        return back()->with('flash', 'Vehicle type deleted.');
    }

    /**
     * @return array<string, mixed>
     */
    protected function validated(Request $request, ?VehicleType $vehicleType = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'slug' => [
                'nullable',
                'string',
                'max:80',
                'regex:/^[a-z0-9-]+$/',
                Rule::unique('vehicle_types', 'slug')->ignore($vehicleType?->id),
            ],
            'description' => ['nullable', 'string', 'max:500'],
            'passenger_capacity' => ['required', 'integer', 'min:1', 'max:60'],
            'luggage_capacity' => ['nullable', 'integer', 'min:0', 'max:60'],
            'is_active' => ['sometimes', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'image_upload' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'remove_image' => ['sometimes', 'boolean'],
        ]);
    }

    private function deleteManagedImage(?string $imagePath): void
    {
        $path = parse_url((string) $imagePath, PHP_URL_PATH) ?: (string) $imagePath;

        if (str_starts_with($path, '/storage/')) {
            $path = substr($path, strlen('/storage/'));
        }

        if (str_starts_with($path, 'taxi/vehicle-types/')) {
            Storage::disk('public')->delete($path);
        }
    }
}
