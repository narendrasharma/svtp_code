<?php

namespace App\Http\Controllers\Taxi;

use App\Enums\TripType;
use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Models\TaxiCancellationPolicy;
use App\Models\VendorProfile;
use App\Support\TaxiSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class TaxiCancellationPolicyController extends Controller
{
    public const SETTINGS = ['cancellation.enabled', 'customer_cancellation.enabled', 'reschedule.enabled', 'customer_reschedule.enabled', 'refunds.enabled', 'cancellation.default_reason_required'];

    private function vendorId(Request $request): ?int
    {
        if (! $request->routeIs('vendor.*')) {
            return null;
        }
        $profile = $request->user()->vendorProfile;
        abort_unless($profile && $profile->is_active, 403);

        return $profile->id;
    }

    public function index(Request $request): Response
    {
        $vendorId = $this->vendorId($request);

        return Inertia::render('Taxi/Changes/Policies', [
            'portal' => $vendorId ? 'vendor' : 'admin',
            'policies' => TaxiCancellationPolicy::when($vendorId, fn ($q) => $q->where('vendor_profile_id', $vendorId))->latest('id')->paginate(15),
            'vendors' => $vendorId ? [] : VendorProfile::orderBy('business_name')->get(['id', 'business_name']),
            'tripTypes' => collect(TripType::cases())->map(fn ($t) => ['value' => $t->value, 'label' => $t->label()]),
            'settings' => collect(self::SETTINGS)->mapWithKeys(fn ($key) => [str_replace('.', '_', $key) => TaxiSettings::get('taxi.'.$key) === '1']),
            'canManage' => $vendorId !== null || $request->user()->can('taxi.cancellations.manage'),
            'canSettings' => $vendorId === null && $request->user()->can('taxi.settings.manage'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        TaxiCancellationPolicy::create($this->data($request));

        return back()->with('flash', 'Cancellation policy created.');
    }

    public function update(Request $request, TaxiCancellationPolicy $policy): RedirectResponse
    {
        $vendorId = $this->vendorId($request);
        abort_if($vendorId !== null && $policy->vendor_profile_id !== $vendorId, 404);
        $policy->update($this->data($request));

        return back()->with('flash', 'Policy updated. Historical cancellations are unchanged.');
    }

    public function toggle(Request $request, TaxiCancellationPolicy $policy): RedirectResponse
    {
        $vendorId = $this->vendorId($request);
        abort_if($vendorId !== null && $policy->vendor_profile_id !== $vendorId, 404);
        $policy->update(['is_active' => ! $policy->is_active]);

        return back()->with('flash', 'Policy activity updated.');
    }

    public function settings(Request $request): RedirectResponse
    {
        foreach (self::SETTINGS as $key) {
            $field = str_replace('.', '_', $key);
            $data = $request->validate([$field => ['required', 'boolean']]);
            Setting::setValue('taxi.'.$key, $data[$field] ? '1' : '0');
        }

        return back()->with('flash', 'Cancellation settings saved.');
    }

    private function data(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'], 'vendor_profile_id' => ['nullable', 'integer', 'exists:vendor_profiles,id'],
            'trip_type' => ['nullable', Rule::in(array_column(TripType::cases(), 'value'))],
            'currency' => ['required', 'regex:/^[A-Za-z]{3}$/'], 'is_active' => ['required', 'boolean'],
            'effective_from' => ['nullable', 'date'], 'effective_until' => ['nullable', 'date', 'after_or_equal:effective_from'],
            'free_cancel_before_minutes' => ['nullable', 'integer', 'min:0', 'max:525600'],
            'fee_type' => ['required', Rule::in(['fixed', 'percentage', 'non_refundable'])],
            'fee_value' => ['required', 'numeric', 'min:0', $request->input('fee_type') === 'percentage' ? 'max:100' : 'max:999999999.99'],
            'no_show_fee_type' => ['required', Rule::in(['fixed', 'percentage', 'non_refundable'])],
            'no_show_fee_value' => ['required', 'numeric', 'min:0', $request->input('no_show_fee_type') === 'percentage' ? 'max:100' : 'max:999999999.99'],
            'minimum_fee' => ['required', 'numeric', 'min:0', 'max:999999999.99'],
            'maximum_fee' => ['nullable', 'numeric', 'gte:minimum_fee', 'max:999999999.99'],
        ]);
        $data['vendor_profile_id'] = $this->vendorId($request) ?? ($data['vendor_profile_id'] ?? null);
        $data['currency'] = strtoupper($data['currency']);

        return $data;
    }
}
