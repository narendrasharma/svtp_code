<?php

namespace App\Http\Controllers\Admin\Hotel;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Support\HotelSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Hotel settings (12B.1). Small deliberate list; safe defaults live in
 * HotelSettings so absent rows never fatal.
 */
class HotelSettingsController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Admin/Hotel/Settings', [
            'settings' => HotelSettings::all(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'hotel_vendor_can_create_properties' => ['required', 'boolean'],
            'hotel_require_property_approval' => ['required', 'boolean'],
            'hotel_default_currency' => ['required', 'string', 'size:3', 'regex:/^[A-Za-z]{3}$/'],
            'hotel_default_timezone' => ['nullable', 'string', 'max:60', 'timezone'],
            'hotel_properties_per_page' => ['required', 'integer', 'min:6', 'max:48'],
            'hotel_show_contact_details' => ['required', 'boolean'],
            'hotel_rooms_show_size' => ['required', 'boolean'],
            'hotel_rooms_show_bed_details' => ['required', 'boolean'],
            'hotel_rooms_units_enabled' => ['required', 'boolean'],
            'hotel_inventory_max_bulk_days' => ['required', 'integer', 'min:1', 'max:730'],
            'hotel_availability_max_stay_nights' => ['required', 'integer', 'min:1', 'max:90'],
            'hotel_availability_public_check_enabled' => ['required', 'boolean'],
            'hotel_pricing_max_bulk_days' => ['required', 'integer', 'min:1', 'max:730'],
            'hotel_pricing_show_tax_breakdown' => ['required', 'boolean'],
            'hotel_pricing_show_nightly_breakdown' => ['required', 'boolean'],
            'hotel_booking_enabled' => ['sometimes', 'boolean'],
            'hotel_booking_require_login' => ['sometimes', 'boolean'],
            'hotel_booking_allow_guest_booking' => ['sometimes', 'boolean'],
            'hotel_booking_default_status' => ['sometimes', 'in:pending,confirmed'],
            'hotel_booking_max_rooms_per_booking' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'hotel_booking_require_terms_acceptance' => ['sometimes', 'boolean'],
            'hotel_reviews_enabled' => ['sometimes', 'boolean'],
            'hotel_reviews_moderation_enabled' => ['sometimes', 'boolean'],
            'hotel_reviews_minimum_comment_length' => ['sometimes', 'integer', 'min:10', 'max:500'],
            'hotel_reviews_vendor_replies_enabled' => ['sometimes', 'boolean'],
        ]);

        Setting::setValue('hotel.vendor_can_create_properties', $validated['hotel_vendor_can_create_properties'] ? '1' : '0');
        Setting::setValue('hotel.require_property_approval', $validated['hotel_require_property_approval'] ? '1' : '0');
        Setting::setValue('hotel.default_currency', strtoupper($validated['hotel_default_currency']));
        Setting::setValue('hotel.default_timezone', $validated['hotel_default_timezone'] ?? '');
        Setting::setValue('hotel.properties_per_page', (string) $validated['hotel_properties_per_page']);
        Setting::setValue('hotel.show_contact_details', $validated['hotel_show_contact_details'] ? '1' : '0');
        Setting::setValue('hotel.rooms.show_size', $validated['hotel_rooms_show_size'] ? '1' : '0');
        Setting::setValue('hotel.rooms.show_bed_details', $validated['hotel_rooms_show_bed_details'] ? '1' : '0');
        Setting::setValue('hotel.rooms.units_enabled', $validated['hotel_rooms_units_enabled'] ? '1' : '0');
        Setting::setValue('hotel.inventory.max_bulk_days', (string) $validated['hotel_inventory_max_bulk_days']);
        Setting::setValue('hotel.availability.max_stay_nights', (string) $validated['hotel_availability_max_stay_nights']);
        Setting::setValue('hotel.availability.public_check_enabled', $validated['hotel_availability_public_check_enabled'] ? '1' : '0');
        Setting::setValue('hotel.pricing.max_bulk_days', (string) $validated['hotel_pricing_max_bulk_days']);
        Setting::setValue('hotel.pricing.show_tax_breakdown', $validated['hotel_pricing_show_tax_breakdown'] ? '1' : '0');
        Setting::setValue('hotel.pricing.show_nightly_breakdown', $validated['hotel_pricing_show_nightly_breakdown'] ? '1' : '0');
        foreach ([
            'hotel_booking_enabled' => 'hotel.booking.enabled',
            'hotel_booking_require_login' => 'hotel.booking.require_login',
            'hotel_booking_allow_guest_booking' => 'hotel.booking.allow_guest_booking',
            'hotel_booking_require_terms_acceptance' => 'hotel.booking.require_terms_acceptance',
            'hotel_reviews_enabled' => 'hotel.reviews.enabled',
            'hotel_reviews_moderation_enabled' => 'hotel.reviews.moderation_enabled',
            'hotel_reviews_vendor_replies_enabled' => 'hotel.reviews.vendor_replies_enabled',
        ] as $input => $key) {
            if (array_key_exists($input, $validated)) {
                Setting::setValue($key, $validated[$input] ? '1' : '0');
            }
        }
        if (array_key_exists('hotel_booking_default_status', $validated)) {
            Setting::setValue('hotel.booking.default_status', $validated['hotel_booking_default_status']);
        }
        if (array_key_exists('hotel_booking_max_rooms_per_booking', $validated)) {
            Setting::setValue('hotel.booking.max_rooms_per_booking', (string) $validated['hotel_booking_max_rooms_per_booking']);
        }
        if (array_key_exists('hotel_reviews_minimum_comment_length', $validated)) {
            Setting::setValue('hotel.reviews.minimum_comment_length', (string) $validated['hotel_reviews_minimum_comment_length']);
        }

        return back()->with('flash', 'Hotel settings updated.');
    }
}
