<?php

namespace App\Http\Controllers;

use App\Enums\BookingSource;
use App\Enums\TripType;
use App\Models\TaxiBooking;
use App\Models\VehicleType;
use App\Services\LeadService;
use App\Services\MoneyPresenter;
use App\Services\TaxiBookingService;
use App\Services\TaxiPricingService;
use App\Services\TaxiRouteService;
use App\Support\TaxiSettings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Public Taxi journey adapter. Quote and booking authority remain in the
 * existing pricing and booking services; the legacy enquiry endpoint stays
 * available for compatibility.
 */
class TaxiEnquiryController extends Controller
{
    public function __construct(
        protected LeadService $leads,
        protected TaxiPricingService $pricing,
        protected TaxiBookingService $bookings,
        protected TaxiRouteService $routes,
    ) {}

    public function show(): Response
    {
        return Inertia::render('Taxi/Enquiry', [
            'vehicleTypes' => VehicleType::active()->get([
                'id', 'name', 'description', 'icon', 'image_path', 'passenger_capacity', 'luggage_capacity',
            ]),
            'tripTypes' => $this->tripTypes(),
            'currency' => strtoupper((string) (TaxiSettings::get('taxi.default_currency') ?? 'INR')),
            'customer' => auth()->user() ? [
                'name' => auth()->user()->name,
                'email' => auth()->user()->email,
                'phone' => auth()->user()->phone,
            ] : null,
        ]);
    }

    public function quote(Request $request): JsonResponse
    {
        $validated = $this->tripData($request);
        $metrics = $this->routeMetrics($validated);
        $options = VehicleType::active()
            ->where('passenger_capacity', '>=', $validated['passenger_count'])
            ->get(['id', 'name', 'description', 'icon', 'image_path', 'passenger_capacity', 'luggage_capacity'])
            ->map(function (VehicleType $vehicleType) use ($validated, $metrics): ?array {
                try {
                    $quote = $this->pricing->quote([
                        ...$validated,
                        'vehicle_type_id' => $vehicleType->id,
                        'currency' => TaxiSettings::get('taxi.default_currency') ?? 'INR',
                        'distance_km' => $metrics['distance_km'],
                        'duration_minutes' => $metrics['duration_minutes'],
                    ]);
                } catch (\Throwable) {
                    return null;
                }

                return $this->option($vehicleType, $quote, $metrics);
            })
            ->filter()
            ->values();

        return response()->json([
            'options' => $options,
            'route' => $metrics,
            'currency' => strtoupper((string) (TaxiSettings::get('taxi.default_currency') ?? 'INR')),
        ]);
    }

    public function book(Request $request): RedirectResponse
    {
        $validated = $this->tripData($request, true);
        $metrics = $this->routeMetrics($validated);
        $vehicleType = VehicleType::active()->findOrFail($validated['vehicle_type_id']);

        if ((int) $vehicleType->passenger_capacity < (int) $validated['passenger_count']) {
            return back()->withErrors(['vehicle_type_id' => 'Choose a vehicle that fits your passenger count.'])->withInput();
        }

        $booking = $this->bookings->create([
            ...$validated,
            'customer_user_id' => $request->user()?->id,
            'source' => BookingSource::Website->value,
            'quoted_distance_km' => $metrics['distance_km'],
            'quoted_duration_minutes' => $metrics['duration_minutes'],
        ], $request->user());

        return redirect()->to(URL::signedRoute('taxi.confirmation', ['taxiBooking' => $booking]))
            ->with('flash', 'Taxi booking created.');
    }

    public function confirmation(TaxiBooking $taxiBooking): Response
    {
        $taxiBooking->load(['vehicleType:id,name,passenger_capacity,luggage_capacity', 'statusHistories:id,taxi_booking_id,to_status,created_at']);

        return Inertia::render('Taxi/Confirmation', [
            'booking' => [
                'reference' => $taxiBooking->reference,
                'pickup_address' => $taxiBooking->pickup_address,
                'drop_address' => $taxiBooking->drop_address,
                'pickup_at' => $taxiBooking->pickup_at?->toISOString(),
                'passenger_count' => $taxiBooking->passenger_count,
                'luggage_count' => $taxiBooking->luggage_count,
                'vehicle' => $taxiBooking->vehicleType ? [
                    'name' => $taxiBooking->vehicleType->name,
                    'passenger_capacity' => $taxiBooking->vehicleType->passenger_capacity,
                ] : null,
                'currency' => $taxiBooking->currency,
                'total' => MoneyPresenter::present($taxiBooking->total_amount, $taxiBooking->currency),
                'status' => $taxiBooking->status,
                'payment_status' => $taxiBooking->payment_status,
                'guest_recovery_available' => filled($taxiBooking->customer_email),
            ],
            'seo' => ['title' => 'Taxi booking confirmation', 'noindex' => true],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'trip_type' => ['required', 'in:one_way,airport_transfer'],
            'pickup_address' => ['required', 'string', 'max:500'],
            'drop_address' => ['required', 'string', 'max:500'],
            'pickup_at' => ['required', 'date', 'after:now'],
            'passenger_count' => ['required', 'integer', 'min:1', 'max:60'],
            'vehicle_type_id' => ['nullable', 'integer', 'exists:vehicle_types,id'],
            'name' => ['required', 'string', 'max:150'],
            'phone' => ['required', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:150'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $vehicleType = isset($validated['vehicle_type_id'])
            ? VehicleType::find($validated['vehicle_type_id'])
            : null;

        $summary = implode("\n", array_filter([
            'Trip: '.($validated['trip_type'] === 'airport_transfer' ? 'Airport Transfer' : 'One Way'),
            'Pickup: '.$validated['pickup_address'].' at '.$validated['pickup_at'],
            'Drop: '.$validated['drop_address'],
            'Passengers: '.$validated['passenger_count'],
            $vehicleType ? 'Vehicle: '.$vehicleType->name : null,
            ! empty($validated['notes']) ? 'Notes: '.$validated['notes'] : null,
        ]));

        $this->leads->createLead([
            'name' => $validated['name'],
            'phone' => $validated['phone'],
            'email' => $validated['email'] ?? null,
            'service_type' => 'taxi',
            'product_title' => $vehicleType?->name,
            'destination' => mb_substr($validated['drop_address'], 0, 150),
            'adults' => $validated['passenger_count'],
            'summary' => $summary,
        ]);

        return back()->with('flash', 'Taxi request received. Our team will confirm your ride shortly.');
    }

    /**
     * @return array<int, array{value:string,label:string}>
     */
    private function tripTypes(): array
    {
        return collect([
            TripType::OneWay,
            TripType::AirportTransfer,
        ])->filter(function (TripType $type): bool {
            return TaxiSettings::enabled($type === TripType::OneWay ? 'taxi.one_way_enabled' : 'taxi.airport_transfer_enabled');
        })->map(fn (TripType $type): array => ['value' => $type->value, 'label' => $type->label()])->values()->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function tripData(Request $request, bool $booking = false): array
    {
        $validated = $request->validate([
            'trip_type' => ['required', 'in:one_way,airport_transfer'],
            'pickup_at' => ['required', 'date', 'after:now'],
            'pickup_address' => ['required', 'string', 'max:500'],
            'pickup_lat' => ['nullable', 'numeric', 'between:-90,90'],
            'pickup_lng' => ['nullable', 'numeric', 'between:-180,180'],
            'drop_address' => ['required', 'string', 'max:500'],
            'drop_lat' => ['nullable', 'numeric', 'between:-90,90'],
            'drop_lng' => ['nullable', 'numeric', 'between:-180,180'],
            'passenger_count' => ['required', 'integer', 'min:1', 'max:60'],
            'luggage_count' => ['nullable', 'integer', 'min:0', 'max:60'],
            'vehicle_type_id' => [$booking ? 'required' : 'nullable', 'integer', 'exists:vehicle_types,id'],
            'airport_direction' => ['nullable', 'in:airport_pickup,airport_drop'],
            'flight_number' => ['nullable', 'string', 'max:20'],
            'airline' => ['nullable', 'string', 'max:80'],
            'terminal' => ['nullable', 'string', 'max:40'],
            'customer_name' => [$booking ? 'required' : 'nullable', 'string', 'max:150'],
            'customer_phone' => [$booking ? 'required' : 'nullable', 'string', 'max:30'],
            'customer_email' => ['nullable', 'email', 'max:150'],
            'special_instructions' => ['nullable', 'string', 'max:2000'],
        ]);

        if ($validated['trip_type'] === TripType::AirportTransfer->value && empty($validated['airport_direction'])) {
            $request->validate(['airport_direction' => ['required']]);
        }

        return $validated;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{distance_km:float,duration_minutes:int,available:bool}
     */
    private function routeMetrics(array $data): array
    {
        if ($data['pickup_lat'] === null || $data['pickup_lng'] === null || $data['drop_lat'] === null || $data['drop_lng'] === null) {
            return ['distance_km' => 0.0, 'duration_minutes' => 0, 'available' => false];
        }

        $route = $this->routes->route((float) $data['pickup_lat'], (float) $data['pickup_lng'], (float) $data['drop_lat'], (float) $data['drop_lng']);

        return [
            'distance_km' => $route->distanceMeters !== null ? round($route->distanceMeters / 1000, 2) : 0.0,
            'duration_minutes' => $route->durationSeconds !== null ? (int) round($route->durationSeconds / 60) : 0,
            'available' => $route->available,
        ];
    }

    /**
     * @param  array<string, mixed>  $metrics
     * @return array<string, mixed>
     */
    private function option(VehicleType $vehicleType, array $quote, array $metrics): array
    {
        return [
            'vehicle' => [
                'id' => $vehicleType->id,
                'name' => $vehicleType->name,
                'description' => $vehicleType->description,
                'icon' => $vehicleType->icon,
                'image' => $this->vehicleTypeImage($vehicleType->image_path),
                'passenger_capacity' => $vehicleType->passenger_capacity,
                'luggage_capacity' => $vehicleType->luggage_capacity,
            ],
            'currency' => $quote['currency'],
            'total' => MoneyPresenter::present($quote['breakdown']['grand_total'], $quote['currency']),
            'breakdown' => $quote['breakdown'],
            'rate_card' => ['name' => $quote['snapshot']['rate_card']['name']],
            'route' => $metrics,
        ];
    }

    private function vehicleTypeImage(?string $imagePath): ?string
    {
        if ($imagePath === null || trim($imagePath) === '') {
            return null;
        }

        if (preg_match('/^https?:\/\//i', $imagePath) === 1) {
            return $imagePath;
        }

        return Storage::disk('public')->url($imagePath);
    }
}
