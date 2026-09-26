<?php

namespace App\AI\Tools;

use App\AI\Contracts\AIToolInterface;
use App\AI\DTOs\AIExecutionContext;
use App\AI\Support\AIToolAction;
use App\Models\Booking;
use App\Models\City;
use App\Models\Destination;
use App\Models\HotelBooking;
use App\Models\Place;
use App\Models\Property;
use App\Models\TaxiBooking;
use App\Models\TourPackage;
use App\Support\ModuleManager;
use Carbon\Carbon;
use Illuminate\Support\Facades\Validator;
use InvalidArgumentException;

class MarketplaceReadTool implements AIToolInterface
{
    public const NAMES = [
        'search_tours', 'get_tour', 'search_hotels', 'get_hotel',
        'search_tour_bookings', 'search_hotel_bookings', 'search_taxi_bookings',
        'marketplace_overview', 'search_geography',
    ];

    public function __construct(private readonly string $toolName)
    {
        if (! in_array($toolName, self::NAMES, true)) {
            throw new InvalidArgumentException('Unknown marketplace tool.');
        }
    }

    public function name(): string
    {
        return $this->toolName;
    }

    public function description(): string
    {
        return match ($this->toolName) {
            'search_tours' => 'Find tours by title, destination, city, active status or featured flag. Returns a bounded list.',
            'get_tour' => 'Get one tour summary, destinations and a short itinerary by ID.',
            'search_hotels' => 'Find hotel properties by name, city or publication status. Does not check room availability.',
            'get_hotel' => 'Get one hotel property summary, room types and amenities by ID. Does not check availability.',
            'search_tour_bookings' => 'Find tour bookings by travel date and status, with no financial or customer details.',
            'search_hotel_bookings' => 'Find hotel bookings by arrival/departure date and status, with no financial or guest details.',
            'search_taxi_bookings' => 'Find taxi bookings by pickup date and status, with no financial or customer details.',
            'marketplace_overview' => 'Count current active inventory and bookings by status. No revenue or currency mixing.',
            default => 'Find cities, destinations and places; destinations include active tour counts.',
        };
    }

    public function action(): AIToolAction
    {
        return AIToolAction::Read;
    }

    public function requiredPermission(): string
    {
        return match ($this->toolName) {
            'search_tours', 'get_tour', 'search_geography' => 'tours.view',
            'search_hotels', 'get_hotel' => 'hotel.properties.view',
            'search_tour_bookings' => 'bookings.view',
            'search_hotel_bookings' => 'hotel.bookings.view',
            'search_taxi_bookings' => 'taxi.bookings.view',
            default => 'reports.view',
        };
    }

    public function available(ModuleManager $modules): bool
    {
        $module = match ($this->toolName) {
            'search_hotels', 'get_hotel', 'search_hotel_bookings' => 'hotels',
            'search_taxi_bookings' => 'taxi',
            'marketplace_overview' => null,
            default => 'tours',
        };

        return $module === null || $modules->isEnabled($module);
    }

    /** @return array<string, mixed> */
    public function inputSchema(): array
    {
        $string = ['type' => 'string'];
        $properties = match ($this->toolName) {
            'get_tour', 'get_hotel' => ['id' => ['type' => 'integer', 'description' => 'Record ID']],
            'search_tours' => ['query' => $string, 'city' => $string, 'destination' => $string, 'status' => ['type' => 'string', 'enum' => ['active', 'inactive']], 'featured' => ['type' => 'boolean'], 'limit' => ['type' => 'integer']],
            'search_hotels' => ['query' => $string, 'city' => $string, 'destination' => $string, 'type' => $string, 'status' => ['type' => 'string', 'enum' => ['draft', 'pending_review', 'published', 'inactive', 'rejected']], 'limit' => ['type' => 'integer']],
            'search_tour_bookings' => ['from' => ['type' => 'string', 'description' => 'Travel date YYYY-MM-DD'], 'to' => ['type' => 'string', 'description' => 'Travel date YYYY-MM-DD'], 'status' => $string, 'payment_status' => $string, 'tour_id' => ['type' => 'integer'], 'limit' => ['type' => 'integer']],
            'search_hotel_bookings' => ['from' => ['type' => 'string', 'description' => 'YYYY-MM-DD'], 'to' => ['type' => 'string', 'description' => 'YYYY-MM-DD'], 'date_field' => ['type' => 'string', 'enum' => ['arrival', 'departure']], 'status' => $string, 'payment_status' => $string, 'property_id' => ['type' => 'integer'], 'limit' => ['type' => 'integer']],
            'search_taxi_bookings' => ['from' => ['type' => 'string', 'description' => 'Pickup date YYYY-MM-DD'], 'to' => ['type' => 'string', 'description' => 'Pickup date YYYY-MM-DD'], 'status' => $string, 'payment_status' => $string, 'vehicle_type_id' => ['type' => 'integer'], 'limit' => ['type' => 'integer']],
            'search_geography' => ['query' => $string, 'type' => ['type' => 'string', 'enum' => ['city', 'destination', 'place']], 'limit' => ['type' => 'integer']],
            'marketplace_overview' => ['date' => ['type' => 'string', 'description' => 'Optional booking creation date YYYY-MM-DD']],
            default => [],
        };

        return ['type' => 'object', 'properties' => $properties, 'required' => in_array($this->toolName, ['get_tour', 'get_hotel'], true) ? ['id'] : []];
    }

    /** @param array<string, mixed> $arguments
     * @return array<string, mixed>
     */
    public function execute(AIExecutionContext $context, array $arguments): array
    {
        if ($context->role !== 'admin' || $context->userId === null) {
            throw new InvalidArgumentException('This tool is unavailable.');
        }

        if (! in_array($this->requiredPermission(), $context->permissions, true) || ! $this->available(app(ModuleManager::class))) {
            throw new InvalidArgumentException('This tool is unavailable for your permissions.');
        }

        $this->validate($arguments);

        return match ($this->toolName) {
            'search_tours' => $this->searchTours($arguments),
            'get_tour' => $this->getTour($arguments),
            'search_hotels' => $this->searchHotels($arguments),
            'get_hotel' => $this->getHotel($arguments),
            'search_tour_bookings' => $this->searchBookings(Booking::class, 'travel_date', 'booking_status', $arguments),
            'search_hotel_bookings' => $this->searchBookings(HotelBooking::class, 'check_in', 'status', $arguments),
            'search_taxi_bookings' => $this->searchBookings(TaxiBooking::class, 'pickup_at', 'status', $arguments),
            'marketplace_overview' => $this->overview($arguments),
            default => $this->searchGeography($arguments),
        };
    }

    /** @param array<string, mixed> $arguments */
    private function validate(array $arguments): void
    {
        $properties = $this->inputSchema()['properties'];

        if (array_diff(array_keys($arguments), array_keys($properties)) !== []) {
            throw new InvalidArgumentException('Unknown tool argument.');
        }

        $rules = [];

        foreach ($properties as $key => $property) {
            $rules[$key] = match ($key) {
                'id' => 'required|integer|min:1',
                'tour_id', 'property_id', 'vehicle_type_id' => 'sometimes|integer|min:1',
                'limit' => 'sometimes|integer|between:1,25',
                'featured' => 'sometimes|boolean',
                'from', 'to', 'date' => 'sometimes|date_format:Y-m-d',
                'status' => 'sometimes|string|in:'.implode(',', $this->statuses()),
                'payment_status' => 'sometimes|string|in:unpaid,partially_paid,paid,refunded,partially_refunded,failed',
                'date_field' => 'sometimes|string|in:arrival,departure',
                'type' => $this->toolName === 'search_geography' ? 'sometimes|string|in:city,destination,place' : 'sometimes|string|max:80',
                default => 'sometimes|string|max:80',
            };
        }

        if (Validator::make($arguments, $rules)->fails()) {
            throw new InvalidArgumentException('Invalid tool arguments. Check IDs, dates, filters and limits.');
        }

        if (isset($arguments['from'], $arguments['to'])
            && ($arguments['from'] > $arguments['to'] || Carbon::parse($arguments['from'])->diffInDays(Carbon::parse($arguments['to'])) > 31)) {
            throw new InvalidArgumentException('Date range must be chronological and no longer than 31 days.');
        }
    }

    /** @return list<string> */
    private function statuses(): array
    {
        return match ($this->toolName) {
            'search_tours' => ['active', 'inactive'],
            'search_hotels' => ['draft', 'pending_review', 'published', 'inactive', 'rejected'],
            'search_tour_bookings' => ['pending', 'confirmed', 'completed', 'cancelled'],
            'search_hotel_bookings' => ['pending', 'confirmed', 'checked_in', 'checked_out', 'completed', 'cancelled', 'no_show'],
            'search_taxi_bookings' => ['draft', 'quoted', 'confirmed', 'driver_assigned', 'en_route', 'arrived', 'passenger_on_board', 'completed', 'cancelled', 'no_show'],
            default => [],
        };
    }

    /** @param array<string, mixed> $arguments
     * @return array<string, mixed>
     */
    private function searchTours(array $arguments): array
    {
        $query = TourPackage::query()->with(['city:id,name', 'destinations:id,name']);
        $query->when($arguments['query'] ?? null, fn ($q, $term) => $q->where('title', 'like', '%'.$term.'%'));
        $query->when($arguments['city'] ?? null, fn ($q, $city) => $q->whereHas('city', fn ($cityQuery) => $cityQuery->where('name', 'like', '%'.$city.'%')));
        $query->when($arguments['destination'] ?? null, fn ($q, $place) => $q->whereHas('destinations', fn ($destinationQuery) => $destinationQuery->where('name', 'like', '%'.$place.'%')));
        $query->when(($arguments['status'] ?? null) === 'active', fn ($q) => $q->publiclyVisible());
        $query->when(($arguments['status'] ?? null) === 'inactive', fn ($q) => $q->where('is_active', false));
        $query->when(isset($arguments['featured']), fn ($q) => $q->where('is_featured', (bool) $arguments['featured']));

        return $this->bounded($query, $arguments, fn (TourPackage $tour): array => [
            'id' => $tour->id, 'title' => $this->short($tour->title),
            'status' => $tour->moderation_status?->value, 'active' => $tour->is_active,
            'featured' => $tour->is_featured, 'city' => $tour->city?->name,
            'destinations' => $tour->destinations->pluck('name')->take(5)->all(),
            'duration_days' => $tour->duration_days,
        ]);
    }

    /** @param array<string, mixed> $arguments
     * @return array<string, mixed>
     */
    private function getTour(array $arguments): array
    {
        $tour = TourPackage::with(['city:id,name', 'destinations:id,name', 'places:id,name'])->find($arguments['id']);

        if (! $tour) {
            return ['found' => false];
        }

        return ['found' => true, 'tour' => [
            'id' => $tour->id, 'title' => $this->short($tour->title), 'status' => $tour->moderation_status?->value,
            'active' => $tour->is_active, 'duration_days' => $tour->duration_days, 'city' => $tour->city?->name,
            'destinations' => $tour->destinations->pluck('name')->take(8)->all(),
            'places' => $tour->places->pluck('name')->take(10)->all(),
            'overview' => $this->short($tour->overview, 450),
            'itinerary' => collect($tour->day_wise_itinerary ?? [])->take(4)->map(fn ($day): array => [
                'day' => $day['day'] ?? null, 'title' => $this->short($day['title'] ?? ''),
                'points' => collect($day['points'] ?? [])->take(3)->map(fn ($point): string => $this->short($point, 180))->all(),
            ])->all(),
        ]];
    }

    /** @param array<string, mixed> $arguments
     * @return array<string, mixed>
     */
    private function searchHotels(array $arguments): array
    {
        $query = Property::query()->with(['city:id,name', 'propertyType:id,name']);
        $query->when($arguments['query'] ?? null, fn ($q, $term) => $q->where('name', 'like', '%'.$term.'%'));
        $query->when($arguments['city'] ?? null, fn ($q, $city) => $q->whereHas('city', fn ($cityQuery) => $cityQuery->where('name', 'like', '%'.$city.'%')));
        $query->when($arguments['destination'] ?? null, fn ($q, $destination) => $q->whereHas('destination', fn ($destinationQuery) => $destinationQuery->where('name', 'like', '%'.$destination.'%')));
        $query->when($arguments['type'] ?? null, fn ($q, $type) => $q->whereHas('propertyType', fn ($typeQuery) => $typeQuery->where('name', 'like', '%'.$type.'%')));
        $query->when($arguments['status'] ?? null, fn ($q, $status) => $q->where('status', $status));

        return $this->bounded($query, $arguments, fn (Property $hotel): array => [
            'id' => $hotel->id, 'name' => $this->short($hotel->name), 'status' => $hotel->status()->value,
            'city' => $hotel->city?->name, 'type' => $hotel->propertyType?->name, 'star_rating' => $hotel->star_rating,
        ]);
    }

    /** @param array<string, mixed> $arguments
     * @return array<string, mixed>
     */
    private function getHotel(array $arguments): array
    {
        $hotel = Property::with(['city:id,name', 'propertyType:id,name', 'amenities:id,name', 'roomTypes:id,property_id,name,status'])->find($arguments['id']);

        if (! $hotel) {
            return ['found' => false];
        }

        return ['found' => true, 'hotel' => [
            'id' => $hotel->id, 'name' => $this->short($hotel->name), 'status' => $hotel->status()->value,
            'city' => $hotel->city?->name, 'type' => $hotel->propertyType?->name,
            'star_rating' => $hotel->star_rating, 'description' => $this->short($hotel->description, 350),
            'amenities' => $hotel->amenities->pluck('name')->take(15)->all(),
            'room_types' => $hotel->roomTypes->take(10)->map(fn ($room): array => ['name' => $this->short($room->name), 'status' => $room->status()->value])->all(),
            'availability_checked' => false,
        ]];
    }

    /** @param class-string $modelClass
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function searchBookings(string $modelClass, string $dateColumn, string $statusColumn, array $arguments): array
    {
        if ($modelClass === HotelBooking::class && ($arguments['date_field'] ?? null) === 'departure') {
            $dateColumn = 'check_out';
        }

        $query = $modelClass::query();
        $query->when($arguments['from'] ?? null, fn ($q, $date) => $q->whereDate($dateColumn, '>=', $date));
        $query->when($arguments['to'] ?? null, fn ($q, $date) => $q->whereDate($dateColumn, '<=', $date));
        $query->when($arguments['status'] ?? null, fn ($q, $status) => $q->where($statusColumn, $status));
        $query->when($arguments['payment_status'] ?? null, fn ($q, $status) => $q->where('payment_status', $status));
        $query->when($arguments['tour_id'] ?? null, fn ($q, $id) => $q->where('package_id', $id));
        $query->when($arguments['property_id'] ?? null, fn ($q, $id) => $q->where('property_id', $id));
        $query->when($arguments['vehicle_type_id'] ?? null, fn ($q, $id) => $q->where('vehicle_type_id', $id));

        return $this->bounded($query, $arguments, function ($booking) use ($dateColumn, $statusColumn): array {
            $status = $booking->{$statusColumn};
            $payment = $booking->payment_status;

            return [
                'id' => $booking->id,
                'reference' => $booking->booking_reference_id ?? $booking->booking_number ?? $booking->reference ?? null,
                'date' => $booking->{$dateColumn}?->toDateString(),
                'status' => is_object($status) ? $status->value : $status,
                'payment_status' => is_object($payment) ? $payment->value : $payment,
                'tour_id' => $booking->package_id ?? null, 'property_id' => $booking->property_id ?? null,
            ];
        });
    }

    /** @param array<string, mixed> $arguments
     * @return array<string, mixed>
     */
    private function overview(array $arguments): array
    {
        $date = $arguments['date'] ?? null;
        $modules = app(ModuleManager::class);

        return [
            'booking_created_on' => $date ?? 'all_dates',
            'active_tours' => $modules->isEnabled('tours') ? TourPackage::publiclyVisible()->count() : null,
            'published_hotels' => $modules->isEnabled('hotels') ? Property::published()->count() : null,
            'tour_bookings' => $modules->isEnabled('tours') ? Booking::query()->when($date, fn ($q) => $q->whereDate('created_at', $date))->count() : null,
            'hotel_bookings' => $modules->isEnabled('hotels') ? HotelBooking::query()->when($date, fn ($q) => $q->whereDate('created_at', $date))->count() : null,
            'taxi_bookings' => $modules->isEnabled('taxi') ? TaxiBooking::query()->when($date, fn ($q) => $q->whereDate('created_at', $date))->count() : null,
            'pending_tour_bookings' => $modules->isEnabled('tours') ? Booking::where('booking_status', 'pending')->when($date, fn ($q) => $q->whereDate('created_at', $date))->count() : null,
            'pending_hotel_bookings' => $modules->isEnabled('hotels') ? HotelBooking::where('status', 'pending')->when($date, fn ($q) => $q->whereDate('created_at', $date))->count() : null,
            'pending_taxi_bookings' => $modules->isEnabled('taxi') ? TaxiBooking::whereIn('status', ['draft', 'quoted'])->when($date, fn ($q) => $q->whereDate('created_at', $date))->count() : null,
            'financial_values_included' => false,
        ];
    }

    /** @param array<string, mixed> $arguments
     * @return array<string, mixed>
     */
    private function searchGeography(array $arguments): array
    {
        $result = [];
        $limit = (int) ($arguments['limit'] ?? 10);
        $term = $arguments['query'] ?? null;
        $type = $arguments['type'] ?? null;
        $perType = $type === null ? min(8, (int) ceil($limit / 3)) : $limit;

        if ($type === null || $type === 'city') {
            $result['cities'] = City::query()->when($term, fn ($q) => $q->where('name', 'like', '%'.$term.'%'))
                ->limit($perType)->get(['id', 'name'])->map(fn (City $city): array => ['id' => $city->id, 'name' => $this->short($city->name)])->all();
        }

        if ($type === null || $type === 'destination') {
            $result['destinations'] = Destination::query()->when($term, fn ($q) => $q->where('name', 'like', '%'.$term.'%'))
                ->withCount(['tourPackages as active_tours_count' => fn ($q) => $q->publiclyVisible()])
                ->orderByDesc('active_tours_count')->limit($perType)->get(['id', 'name', 'city_id'])
                ->map(fn (Destination $destination): array => [
                    'id' => $destination->id, 'name' => $this->short($destination->name),
                    'active_tours_count' => $destination->active_tours_count,
                ])->all();
        }

        if ($type === null || $type === 'place') {
            $result['places'] = Place::query()->when($term, fn ($q) => $q->where(fn ($match) => $match
                ->where('name', 'like', '%'.$term.'%')
                ->orWhereHas('destination', fn ($destination) => $destination->where('name', 'like', '%'.$term.'%'))))
                ->with('destination:id,name')->limit($perType)->get(['id', 'name', 'destination_id'])
                ->map(fn (Place $place): array => ['id' => $place->id, 'name' => $this->short($place->name), 'destination' => $place->destination?->name])->all();
        }

        return ['items' => $result, 'limited' => true, 'limit' => $limit];
    }

    /** @param array<string, mixed> $arguments
     * @return array<string, mixed>
     */
    private function bounded($query, array $arguments, callable $map): array
    {
        $limit = (int) ($arguments['limit'] ?? 10);
        $rows = $query->orderByDesc('id')->limit($limit + 1)->get();

        return [
            'items' => $rows->take($limit)->map($map)->values()->all(),
            'limited' => $rows->count() > $limit,
            'limit' => $limit,
        ];
    }

    private function short(?string $value, int $limit = 160): string
    {
        return mb_substr(trim(strip_tags((string) $value)), 0, $limit);
    }
}
