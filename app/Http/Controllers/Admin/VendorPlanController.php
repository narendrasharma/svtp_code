<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\VendorPlan;
use App\Models\VendorPlanFeature;
use App\Models\VendorProfile;
use App\Services\VendorEntitlementService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class VendorPlanController extends Controller
{
    use AuthorizesRequests;

    public function index(): Response
    {
        $this->authorize('viewAny', VendorPlan::class);

        $plans = VendorPlan::with('features')->withCount(['assignments as vendors_count' => function ($query): void {
            $query->select(DB::raw('COUNT(DISTINCT vendor_profile_id)'));
        }])->orderBy('sort_order')->orderBy('name')->get();

        return Inertia::render('Admin/VendorPlans/Index', [
            'plans' => $plans,
        ]);
    }

    public function create(): Response
    {
        $this->authorize('create', VendorPlan::class);

        return Inertia::render('Admin/VendorPlans/Form', [
            'plan' => null,
            'supportedKeys' => VendorPlan::supportedKeys(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', VendorPlan::class);

        $data = $this->validatePlan($request);

        $plan = DB::transaction(function () use ($data): VendorPlan {
            $plan = VendorPlan::create([
                'name' => $data['name'],
                'slug' => $data['slug'],
                'description' => $data['description'] ?? null,
                'is_active' => (bool) ($data['is_active'] ?? true),
                'is_default' => false,
                'sort_order' => (int) ($data['sort_order'] ?? 0),
            ]);

            $this->syncFeatures($plan, $data['features'] ?? []);

            if (! empty($data['is_default'])) {
                $this->makeDefault($plan);
            }

            return $plan;
        });

        return redirect()->route('admin.vendor-plans.index')->with('flash', "Plan {$plan->name} created.");
    }

    public function edit(VendorPlan $vendorPlan): Response
    {
        $this->authorize('update', $vendorPlan);
        $vendorPlan->load('features');

        return Inertia::render('Admin/VendorPlans/Form', [
            'plan' => $vendorPlan,
            'supportedKeys' => VendorPlan::supportedKeys(),
            'vendorsCount' => $vendorPlan->assignments()->distinct('vendor_profile_id')->count('vendor_profile_id'),
        ]);
    }

    public function update(Request $request, VendorPlan $vendorPlan): RedirectResponse
    {
        $this->authorize('update', $vendorPlan);

        $data = $this->validatePlan($request, $vendorPlan->id);

        DB::transaction(function () use ($vendorPlan, $data): void {
            $vendorPlan->update([
                'name' => $data['name'],
                'slug' => $data['slug'],
                'description' => $data['description'] ?? null,
                'is_active' => (bool) ($data['is_active'] ?? true),
                'sort_order' => (int) ($data['sort_order'] ?? 0),
            ]);

            $this->syncFeatures($vendorPlan, $data['features'] ?? []);

            if (! empty($data['is_default'])) {
                $this->makeDefault($vendorPlan);
            } elseif ($vendorPlan->is_default && empty($data['is_default'])) {
                // Never leave the marketplace without a default while active
                // plans exist — unsetting requires picking another default.
                $otherDefault = VendorPlan::where('id', '!=', $vendorPlan->id)->where('is_default', true)->exists();

                if (! $otherDefault) {
                    throw ValidationException::withMessages(['is_default' => 'Another default plan must be set first.']);
                }

                $vendorPlan->update(['is_default' => false]);
            }
        });

        return redirect()->route('admin.vendor-plans.index')->with('flash', "Plan {$vendorPlan->name} updated. Existing vendor content is unchanged.");
    }

    public function toggle(VendorPlan $vendorPlan): RedirectResponse
    {
        $this->authorize('update', $vendorPlan);

        if ($vendorPlan->is_active && $vendorPlan->is_default) {
            return back()->withErrors(['plan' => 'The default plan cannot be deactivated. Set another default first.']);
        }

        $vendorPlan->update(['is_active' => ! $vendorPlan->is_active]);

        return back()->with('flash', $vendorPlan->is_active ? 'Plan activated.' : 'Plan deactivated. Historical assignments remain.');
    }

    public function destroy(VendorPlan $vendorPlan): RedirectResponse
    {
        $this->authorize('delete', $vendorPlan);

        if ($vendorPlan->assignments()->exists()) {
            return back()->withErrors(['plan' => 'This plan has assignment history. Deactivate it instead of deleting.']);
        }

        if ($vendorPlan->is_default) {
            return back()->withErrors(['plan' => 'The default plan cannot be deleted.']);
        }

        $vendorPlan->features()->delete();
        $vendorPlan->delete();

        return redirect()->route('admin.vendor-plans.index')->with('flash', 'Plan deleted.');
    }

    public function assign(Request $request, VendorProfile $vendorProfile): RedirectResponse
    {
        $this->authorize('assign', VendorPlan::class);

        $validated = $request->validate([
            'vendor_plan_id' => ['required', 'integer', 'exists:vendor_plans,id'],
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        $plan = VendorPlan::findOrFail($validated['vendor_plan_id']);

        app(VendorEntitlementService::class)->assignPlan(
            $vendorProfile,
            $plan,
            $request->user()->id,
            $validated['note'] ?? null
        );

        return back()->with('flash', "Vendor moved to {$plan->name}. Existing content is kept; limits apply to new actions.");
    }

    /**
     * @return array<string, mixed>
     */
    protected function validatePlan(Request $request, ?int $ignoreId = null): array
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:100', Rule::unique('vendor_plans', 'slug')->ignore($ignoreId)],
            'description' => ['nullable', 'string', 'max:2000'],
            'is_active' => ['nullable', 'boolean'],
            'is_default' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:1000'],
            'features' => ['nullable', 'array'],
            'features.*.key' => ['required_with:features', 'string', Rule::in(VendorPlan::supportedKeys())],
            'features.*.value_type' => ['required_with:features', 'string', Rule::in(VendorPlanFeature::valueTypes())],
            'features.*.integer_value' => ['nullable', 'integer', 'min:0', 'max:1000000'],
            'features.*.boolean_value' => ['nullable', 'boolean'],
            'features.*.string_value' => ['nullable', 'string', 'max:100'],
        ]);

        if (empty($validated['slug'])) {
            $base = Str::slug($validated['name']) ?: 'plan';
            $slug = $base;
            $i = 2;

            while (VendorPlan::where('slug', $slug)->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))->exists()) {
                $slug = $base.'-'.$i++;
            }

            $validated['slug'] = $slug;
        } else {
            $validated['slug'] = Str::slug($validated['slug']);
        }

        return $validated;
    }

    /**
     * @param  array<int, array<string, mixed>>  $features
     */
    protected function syncFeatures(VendorPlan $plan, array $features): void
    {
        foreach ($features as $row) {
            if (! isset($row['key']) || ! in_array($row['key'], VendorPlan::supportedKeys(), true)) {
                continue;
            }

            $type = $row['value_type'] ?? VendorPlanFeature::TYPE_UNLIMITED;

            if (! in_array($type, VendorPlanFeature::valueTypes(), true)) {
                continue;
            }

            $plan->features()->updateOrCreate(
                ['key' => $row['key']],
                [
                    'label' => $row['key'],
                    'value_type' => $type,
                    'integer_value' => $type === 'integer' ? ($row['integer_value'] ?? null) : null,
                    'boolean_value' => $type === 'boolean' ? (bool) ($row['boolean_value'] ?? false) : null,
                    'string_value' => $type === 'string' ? ($row['string_value'] ?? null) : null,
                ]
            );
        }
    }

    protected function makeDefault(VendorPlan $plan): void
    {
        if (! $plan->is_active) {
            throw ValidationException::withMessages(['is_default' => 'Only an active plan can be the default.']);
        }

        VendorPlan::where('id', '!=', $plan->id)->where('is_default', true)->update(['is_default' => false]);
        $plan->update(['is_default' => true]);
    }
}
