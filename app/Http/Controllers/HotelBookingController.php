<?php

namespace App\Http\Controllers;

use App\Models\Property;
use App\Services\HotelBookingService;
use App\Services\MoneyPresenter;
use App\Support\HotelSettings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class HotelBookingController extends Controller
{
    public function __construct(protected HotelBookingService $bookings) {}

    public function create(Request $request, string $slug): Response|RedirectResponse
    {
        if (! HotelSettings::enabled('hotel.booking.enabled')) {
            abort(404);
        }
        if (! $request->user() && ! HotelSettings::enabled('hotel.booking.allow_guest_booking')) {
            return redirect()->guest(route('login'));
        }

        $data = $request->validate($this->selectionRules());

        $property = Property::published()
            ->with(['propertyType:id,name', 'city:id,name', 'destination:id,name', 'images'])
            ->where('slug', $slug)
            ->firstOrFail();
        $quote = $this->bookings->quoteSelection($property, $data);
        $primary = $property->images->firstWhere('is_primary', true) ?? $property->images->first();

        return Inertia::render('Hotels/Booking', [
            'property' => [
                'name' => $property->name,
                'slug' => $property->slug,
                'type' => $property->propertyType?->name,
                'city' => $property->city?->name,
                'destination' => $property->destination?->name,
                'image' => $primary?->url(),
            ],
            'selection' => $this->presentQuote($quote),
            'stay' => [
                'check_in' => $data['check_in'],
                'check_out' => $data['check_out'],
                'rooms' => $quote['rooms_count'],
                'adults' => (int) $data['adults'],
                'children' => (int) ($data['children'] ?? 0),
            ],
            'customer' => [
                'name' => $request->user()?->name,
                'email' => $request->user()?->email,
                'phone' => $request->user()?->phone,
            ],
            'requiresTerms' => HotelSettings::enabled('hotel.booking.require_terms_acceptance'),
            'seo' => [
                'title' => __('common.complete_booking').' · '.$property->name,
                'description' => __('common.complete_booking_description'),
            ],
        ]);
    }

    public function quote(Request $request, string $slug): JsonResponse
    {
        $data = $request->validate($this->selectionRules());
        $property = Property::published()->where('slug', $slug)->firstOrFail();

        return response()->json($this->presentQuote($this->bookings->quoteSelection($property, $data)));
    }

    public function store(Request $request, string $slug): RedirectResponse
    {
        if (! HotelSettings::enabled('hotel.booking.enabled')) {
            abort(404);
        }

        if (! $request->user() && ! HotelSettings::enabled('hotel.booking.allow_guest_booking')) {
            return redirect()->route('login')->with('status', 'Please sign in to reserve a hotel room.');
        }

        $data = $request->validate($this->selectionRules() + [
            'guest_name' => ['required', 'string', 'max:150'],
            'guest_email' => ['required', 'email', 'max:150'],
            'guest_phone' => ['required', 'string', 'max:40'],
            'special_requests' => ['nullable', 'string', 'max:2000'],
            'idempotency_key' => ['nullable', 'string', 'max:120'],
            'quote_fingerprint' => ['nullable', 'string', 'size:64'],
            'terms_accepted' => ['nullable', 'boolean'],
        ]);

        $property = Property::published()->where('slug', $slug)->firstOrFail();
        $booking = $this->bookings->create($data + ['property_id' => $property->id], $request->user(), $request->user());
        if (! $request->user()) {
            $request->session()->put('hotel_booking_confirmation_id', $booking->id);
        }

        return redirect()->route('hotel-booking.confirmation', $booking);
    }

    /** @return array<string, array<int, string>> */
    protected function selectionRules(): array
    {
        return [
            'items' => ['required_without:room_type_id', 'array', 'min:1', 'max:10'],
            'items.*.room_type_id' => ['required', 'integer', 'min:1', 'distinct'],
            'items.*.rate_plan_id' => ['required', 'integer', 'min:1'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:10'],
            'room_type_id' => ['required_without:items', 'integer', 'min:1'],
            'rate_plan_id' => ['required_without:items', 'integer', 'min:1'],
            'rooms' => ['required_without:items', 'integer', 'min:1', 'max:10'],
            'check_in' => ['required', 'date_format:Y-m-d'],
            'check_out' => ['required', 'date_format:Y-m-d', 'after:check_in'],
            'adults' => ['required', 'integer', 'min:1', 'max:20'],
            'children' => ['sometimes', 'integer', 'min:0', 'max:20'],
        ];
    }

    /** @param array<string, mixed> $quote
     * @return array<string, mixed>
     */
    protected function presentQuote(array $quote): array
    {
        $currency = $quote['currency'];

        return [
            'items' => array_map(fn (array $line): array => [
                'room_type_id' => $line['room_type_id'], 'rate_plan_id' => $line['rate_plan_id'],
                'quantity' => $line['quantity'], 'room_name' => $line['room_name'], 'rate_name' => $line['rate_name'],
                'meal_plan' => $line['meal_plan'], 'cancellation_mode' => $line['cancellation_mode'], 'cancellation_note' => $line['cancellation_note'],
                'display_total' => MoneyPresenter::present($line['quote']['total'], $currency),
            ], $quote['items']),
            'rooms_count' => $quote['rooms_count'], 'nights_count' => $quote['nights_count'],
            'subtotal' => $quote['subtotal'], 'taxes' => $quote['taxes'], 'fees' => $quote['fees'], 'total' => $quote['total'],
            'display_subtotal' => MoneyPresenter::present($quote['subtotal'], $currency),
            'display_taxes' => MoneyPresenter::present($quote['taxes'], $currency),
            'display_fees' => MoneyPresenter::present($quote['fees'], $currency),
            'display_total' => MoneyPresenter::present($quote['total'], $currency),
            'quote_fingerprint' => $quote['quote_fingerprint'],
        ];
    }
}
