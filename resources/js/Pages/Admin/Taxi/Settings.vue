<script setup>
import AdminLayout from '../../../Layouts/AdminLayout.vue';
import { Link, useForm } from '@inertiajs/vue3';
import { appUrl } from '../../../appUrl';

const props = defineProps({
    settings: { type: Object, default: () => ({}) },
});

function boolSetting(key, fallback = true) {
    const value = props.settings?.[key];
    if (value === undefined || value === null || value === '') {
        return fallback;
    }
    return ['1', 'true', 'yes', 'on'].includes(String(value).toLowerCase());
}

const form = useForm({
    taxi_booking_enabled: boolSetting('taxi.booking_enabled', true),
    taxi_one_way_enabled: boolSetting('taxi.one_way_enabled', true),
    taxi_airport_transfer_enabled: boolSetting('taxi.airport_transfer_enabled', true),
    taxi_default_currency: props.settings['taxi.default_currency'] ?? 'INR',
    taxi_min_advance_minutes: Number(props.settings['taxi.min_advance_minutes'] ?? 60),
    taxi_max_advance_days: props.settings['taxi.max_advance_days'] ? Number(props.settings['taxi.max_advance_days']) : '',
    taxi_allow_guest_booking: boolSetting('taxi.allow_guest_booking', true),
    taxi_default_assignment_mode: props.settings['taxi.default_assignment_mode'] ?? 'manual',
    taxi_tracking_stale_seconds: Number(props.settings['taxi.tracking_stale_seconds'] ?? 120),
    taxi_location_retention_days: Number(props.settings['taxi.location_retention_days'] ?? 30),
    taxi_maps_provider: props.settings['taxi.maps.provider'] ?? 'none',
    taxi_maps_enabled: boolSetting('taxi.maps.enabled', false),
    taxi_maps_google_browser_key: props.settings['taxi.maps.google.browser_key'] ?? '',
    taxi_maps_google_server_key: props.settings['taxi.maps.google.server_key'] ?? '',
    taxi_maps_mapbox_public_token: props.settings['taxi.maps.mapbox.public_token'] ?? '',
    taxi_maps_mapbox_server_token: props.settings['taxi.maps.mapbox.server_token'] ?? '',
    taxi_routing_enabled: boolSetting('taxi.routing.enabled', false),
    taxi_routing_cache_minutes: Number(props.settings['taxi.routing.cache_minutes'] ?? 10),
    taxi_routing_refresh_seconds: Number(props.settings['taxi.routing.refresh_seconds'] ?? 60),
    taxi_dispatch_smart_enabled: boolSetting('taxi.dispatch.smart_enabled', true),
    taxi_dispatch_max_pickup_radius_km: Number(props.settings['taxi.dispatch.max_pickup_radius_km'] ?? 0),
    taxi_dispatch_routing_candidate_limit: Number(props.settings['taxi.dispatch.routing_candidate_limit'] ?? 5),
    taxi_dispatch_use_routing_eta: boolSetting('taxi.dispatch.use_routing_eta', true),
    taxi_dispatch_auto_enabled: boolSetting('taxi.dispatch.auto_enabled', false),
    taxi_dispatch_offer_enabled: boolSetting('taxi.dispatch.offer_enabled', true),
    taxi_dispatch_offer_timeout_seconds: Number(props.settings['taxi.dispatch.offer_timeout_seconds'] ?? 120),
    taxi_dispatch_max_offer_attempts: Number(props.settings['taxi.dispatch.max_offer_attempts'] ?? 3),
    taxi_dispatch_require_driver_acceptance: boolSetting('taxi.dispatch.require_driver_acceptance', true),
    taxi_dispatch_auto_fallback_manual: boolSetting('taxi.dispatch.auto_fallback_manual', true),
    taxi_customer_tracking_enabled: boolSetting('taxi.customer_tracking.enabled', false),
    taxi_customer_tracking_token_expiry_hours: props.settings['taxi.customer_tracking.token_expiry_hours'] ? Number(props.settings['taxi.customer_tracking.token_expiry_hours']) : '',
    taxi_customer_tracking_show_driver_phone: boolSetting('taxi.customer_tracking.show_driver_phone', false),
    taxi_customer_tracking_show_vehicle_registration: boolSetting('taxi.customer_tracking.show_vehicle_registration', false),
    taxi_customer_tracking_show_route: boolSetting('taxi.customer_tracking.show_route', true),
    taxi_customer_tracking_refresh_seconds: Number(props.settings['taxi.customer_tracking.refresh_seconds'] ?? 30),
    taxi_reviews_enabled: boolSetting('taxi.reviews.enabled', true),
    taxi_reviews_require_moderation: boolSetting('taxi.reviews.require_moderation', true),
    taxi_reviews_review_window_days: props.settings['taxi.reviews.review_window_days'] ? Number(props.settings['taxi.reviews.review_window_days']) : '',
    taxi_reviews_allow_text_review: boolSetting('taxi.reviews.allow_text_review', true),
    taxi_reviews_allow_vendor_reply: boolSetting('taxi.reviews.allow_vendor_reply', true),
    taxi_reviews_show_driver_rating_publicly: boolSetting('taxi.reviews.show_driver_rating_publicly', false),
    taxi_reviews_show_vendor_rating_publicly: boolSetting('taxi.reviews.show_vendor_rating_publicly', false),
    taxi_reviews_minimum_reviews_for_public_average: Number(props.settings['taxi.reviews.minimum_reviews_for_public_average'] ?? 5),
    taxi_reviews_low_rating_threshold: Number(props.settings['taxi.reviews.low_rating_threshold'] ?? 2),
});

function submit() {
    form.post(appUrl('/admin/taxi/settings'), {
        preserveScroll: true,
    });
}
</script>

<template>
    <AdminLayout>
        <div class="d-flex align-items-center justify-content-between flex-wrap gap-3 mb-4">
            <div>
                <h2 class="mt-2 mb-1">Taxi module settings</h2>
                <p class="text-muted mb-0">Control taxi booking availability and booking guardrails.</p>
            </div>
            <Link :href="appUrl('/admin/taxi/bookings')" class="btn btn-outline-light">View taxi bookings</Link>
        </div>

        <form class="card" @submit.prevent="submit">
            <div class="card-body">
                <h5 class="card-title mb-4">Booking availability</h5>
                <div class="row g-3">
                    <div class="col-md-4">
                        <div class="form-check form-switch">
                            <input id="booking-enabled" v-model="form.taxi_booking_enabled" type="checkbox" class="form-check-input" />
                            <label class="form-check-label" for="booking-enabled">Taxi booking enabled</label>
                        </div>
                        <div class="form-text">Toggle the entire taxi module on/off for staff and vendors.</div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-check form-switch">
                            <input id="one-way" v-model="form.taxi_one_way_enabled" type="checkbox" class="form-check-input" />
                            <label class="form-check-label" for="one-way">One-way rides enabled</label>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-check form-switch">
                            <input id="airport" v-model="form.taxi_airport_transfer_enabled" type="checkbox" class="form-check-input" />
                            <label class="form-check-label" for="airport">Airport transfers enabled</label>
                        </div>
                    </div>
                </div>

                <hr class="my-4" />

                <h5 class="card-title mb-3">Booking window</h5>
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label small">Minimum advance (minutes)</label>
                        <input v-model.number="form.taxi_min_advance_minutes" type="number" min="0" max="10080" class="form-control" />
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small">Maximum advance (days)</label>
                        <input v-model="form.taxi_max_advance_days" type="number" min="1" max="365" class="form-control" placeholder="Leave blank for no limit" />
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small">Default currency</label>
                        <input v-model="form.taxi_default_currency" class="form-control text-uppercase" maxlength="3" pattern="[A-Za-z]{3}" placeholder="ISO code, e.g. USD" />
                    </div>
                </div>

                <hr class="my-4" />

                <h5 class="card-title mb-3">Booking controls</h5>
                <div class="row g-3">
                    <div class="col-md-6">
                        <div class="form-check form-switch">
                            <input id="guest" v-model="form.taxi_allow_guest_booking" type="checkbox" class="form-check-input" />
                            <label class="form-check-label" for="guest">Allow guest bookings (no customer account)</label>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small">Assignment mode</label>
                        <select v-model="form.taxi_default_assignment_mode" class="form-select">
                            <option value="manual">Manual assignment</option>
                        </select>
                        <div class="form-text">Automatic dispatch is planned for a future phase.</div>
                    </div>
                </div>

                <h5 class="card-title mt-4 mb-3">Driver tracking</h5>
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label small">Tracking stale threshold (seconds)</label>
                        <input v-model="form.taxi_tracking_stale_seconds" type="number" min="30" max="3600" class="form-control" required />
                        <div class="form-text">A driver position older than this shows as stale.</div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small">Location retention (days)</label>
                        <input v-model="form.taxi_location_retention_days" type="number" min="1" max="365" class="form-control" required />
                        <div class="form-text">Raw location pings older than this are purged by the daily cleanup. Booking records are never deleted.</div>
                    </div>
                </div>

                <h5 class="card-title mt-4 mb-3">Maps &amp; routing</h5>
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label small">Map provider</label>
                        <select v-model="form.taxi_maps_provider" class="form-select">
                            <option value="none">None (coordinates only)</option>
                            <option value="google">Google Maps</option>
                            <option value="mapbox">Mapbox (adapter seam — not implemented yet)</option>
                        </select>
                        <div class="form-text">With None, tracking still works as coordinates and freshness.</div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-check form-switch mt-4">
                            <input id="maps-enabled" v-model="form.taxi_maps_enabled" type="checkbox" class="form-check-input" />
                            <label class="form-check-label" for="maps-enabled">Show maps in tracking views</label>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-check form-switch mt-4">
                            <input id="routing-enabled" v-model="form.taxi_routing_enabled" type="checkbox" class="form-check-input" />
                            <label class="form-check-label" for="routing-enabled">Enable route / ETA lookup</label>
                        </div>
                    </div>
                    <template v-if="form.taxi_maps_provider === 'google'">
                        <div class="col-md-6">
                            <label class="form-label small">Google browser Maps key</label>
                            <input v-model="form.taxi_maps_google_browser_key" type="text" class="form-control" maxlength="255" autocomplete="off" />
                            <div class="form-text">Public key loaded by the browser to render maps. Restrict it to your domains in Google Cloud.</div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small">Google server routing key</label>
                            <input v-model="form.taxi_maps_google_server_key" type="password" class="form-control" maxlength="255" autocomplete="new-password" />
                            <div class="form-text">Secret key used server-side for route / ETA lookup. Never put this in a browser field. A provider account with billing may be required.</div>
                        </div>
                    </template>
                    <template v-if="form.taxi_maps_provider === 'mapbox'">
                        <div class="col-md-6">
                            <label class="form-label small">Mapbox public token</label>
                            <input v-model="form.taxi_maps_mapbox_public_token" type="text" class="form-control" maxlength="255" autocomplete="off" />
                            <div class="form-text">Reserved for the future Mapbox adapter. Not used yet.</div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small">Mapbox server token</label>
                            <input v-model="form.taxi_maps_mapbox_server_token" type="password" class="form-control" maxlength="255" autocomplete="new-password" />
                            <div class="form-text">Reserved for the future Mapbox adapter. Keep secret.</div>
                        </div>
                    </template>
                    <div class="col-md-6">
                        <label class="form-label small">Route cache (minutes)</label>
                        <input v-model="form.taxi_routing_cache_minutes" type="number" min="1" max="120" class="form-control" required />
                        <div class="form-text">Identical origin/destination lookups reuse the cached route.</div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small">Route refresh (seconds)</label>
                        <input v-model="form.taxi_routing_refresh_seconds" type="number" min="30" max="300" class="form-control" required />
                        <div class="form-text">How often tracking views refresh route / ETA while a trip is active.</div>
                    </div>
                </div>

                <h5 class="card-title mt-4 mb-3">Smart dispatch</h5>
                <div class="row g-3">
                    <div class="col-md-6">
                        <div class="form-check form-switch">
                            <input id="smart-enabled" v-model="form.taxi_dispatch_smart_enabled" type="checkbox" class="form-check-input" />
                            <label class="form-check-label" for="smart-enabled">Smart driver recommendations enabled</label>
                        </div>
                        <div class="form-text">Decision support only — assignment always stays manual.</div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-check form-switch mt-md-0 mt-2">
                            <input id="use-routing-eta" v-model="form.taxi_dispatch_use_routing_eta" type="checkbox" class="form-check-input" />
                            <label class="form-check-label" for="use-routing-eta">Rank by routing ETA when available</label>
                        </div>
                        <div class="form-text">Falls back to straight-line distance when routing is unavailable.</div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small">Max pickup radius (km, 0 = unlimited)</label>
                        <input v-model="form.taxi_dispatch_max_pickup_radius_km" type="number" min="0" max="500" step="0.5" class="form-control" required />
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small">Routing candidate limit</label>
                        <input v-model="form.taxi_dispatch_routing_candidate_limit" type="number" min="1" max="20" class="form-control" required />
                        <div class="form-text">Paid routing ETA is calculated only for this many nearest candidates.</div>
                    </div>
                </div>

                <h5 class="card-title mt-4 mb-3">Controlled auto-dispatch</h5>
                <div class="row g-3">
                    <div class="col-md-6">
                        <div class="form-check form-switch">
                            <input id="auto-enabled" v-model="form.taxi_dispatch_auto_enabled" type="checkbox" class="form-check-input" />
                            <label class="form-check-label" for="auto-enabled">Auto-dispatch enabled</label>
                        </div>
                        <div class="form-text">Off by default. Manual Smart Dispatch always keeps working.</div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-check form-switch">
                            <input id="offer-enabled" v-model="form.taxi_dispatch_offer_enabled" type="checkbox" class="form-check-input" />
                            <label class="form-check-label" for="offer-enabled">Driver offer flow enabled</label>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-check form-switch">
                            <input id="require-acceptance" v-model="form.taxi_dispatch_require_driver_acceptance" type="checkbox" class="form-check-input" />
                            <label class="form-check-label" for="require-acceptance">Require driver acceptance</label>
                        </div>
                        <div class="form-text">Drivers must explicitly accept; direct auto-assignment is not supported in this phase.</div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-check form-switch">
                            <input id="fallback-manual" v-model="form.taxi_dispatch_auto_fallback_manual" type="checkbox" class="form-check-input" />
                            <label class="form-check-label" for="fallback-manual">Fall back to manual queue when exhausted</label>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small">Offer timeout (seconds)</label>
                        <input v-model="form.taxi_dispatch_offer_timeout_seconds" type="number" min="30" max="600" class="form-control" required />
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small">Max offer attempts</label>
                        <input v-model="form.taxi_dispatch_max_offer_attempts" type="number" min="1" max="10" class="form-control" required />
                    </div>
                </div>

                <h5 class="card-title mt-4 mb-3">Customer live tracking</h5>
                <div class="row g-3">
                    <div class="col-md-6">
                        <div class="form-check form-switch">
                            <input id="customer-tracking" v-model="form.taxi_customer_tracking_enabled" type="checkbox" class="form-check-input" />
                            <label class="form-check-label" for="customer-tracking">Customer tracking links enabled</label>
                        </div>
                        <div class="form-text">Off by default. Customers open trips through secure revocable links.</div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small">Link expiry (hours, empty = no expiry)</label>
                        <input v-model="form.taxi_customer_tracking_token_expiry_hours" type="number" min="1" max="720" class="form-control" />
                    </div>
                    <div class="col-md-4">
                        <div class="form-check form-switch">
                            <input id="show-driver-phone" v-model="form.taxi_customer_tracking_show_driver_phone" type="checkbox" class="form-check-input" />
                            <label class="form-check-label" for="show-driver-phone">Show driver phone</label>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-check form-switch">
                            <input id="show-registration" v-model="form.taxi_customer_tracking_show_vehicle_registration" type="checkbox" class="form-check-input" />
                            <label class="form-check-label" for="show-registration">Show vehicle registration</label>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-check form-switch">
                            <input id="show-route" v-model="form.taxi_customer_tracking_show_route" type="checkbox" class="form-check-input" />
                            <label class="form-check-label" for="show-route">Show route / ETA</label>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small">Customer refresh (seconds, 15–60)</label>
                        <input v-model="form.taxi_customer_tracking_refresh_seconds" type="number" min="15" max="60" class="form-control" required />
                    </div>
                </div>

                <h5 class="card-title mt-4 mb-3">Reviews &amp; service quality</h5>
                <div class="row g-3">
                    <div class="col-md-4">
                        <div class="form-check form-switch">
                            <input id="reviews-enabled" v-model="form.taxi_reviews_enabled" type="checkbox" class="form-check-input" />
                            <label class="form-check-label" for="reviews-enabled">Taxi reviews enabled</label>
                        </div>
                        <div class="form-text">Completed trips can be rated by their customer.</div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-check form-switch">
                            <input id="reviews-moderation" v-model="form.taxi_reviews_require_moderation" type="checkbox" class="form-check-input" />
                            <label class="form-check-label" for="reviews-moderation">Require moderation</label>
                        </div>
                        <div class="form-text">Off = eligible reviews publish immediately.</div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-check form-switch">
                            <input id="reviews-text" v-model="form.taxi_reviews_allow_text_review" type="checkbox" class="form-check-input" />
                            <label class="form-check-label" for="reviews-text">Allow text reviews</label>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-check form-switch">
                            <input id="reviews-reply" v-model="form.taxi_reviews_allow_vendor_reply" type="checkbox" class="form-check-input" />
                            <label class="form-check-label" for="reviews-reply">Allow vendor replies</label>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-check form-switch">
                            <input id="reviews-public-driver" v-model="form.taxi_reviews_show_driver_rating_publicly" type="checkbox" class="form-check-input" />
                            <label class="form-check-label" for="reviews-public-driver">Public driver rating</label>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-check form-switch">
                            <input id="reviews-public-vendor" v-model="form.taxi_reviews_show_vendor_rating_publicly" type="checkbox" class="form-check-input" />
                            <label class="form-check-label" for="reviews-public-vendor">Public vendor rating</label>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small">Review window (days, empty = no limit)</label>
                        <input v-model="form.taxi_reviews_review_window_days" type="number" min="1" max="365" class="form-control" />
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small">Min reviews for public average</label>
                        <input v-model="form.taxi_reviews_minimum_reviews_for_public_average" type="number" min="1" max="100" class="form-control" required />
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small">Low-rating alert threshold (1–5)</label>
                        <input v-model="form.taxi_reviews_low_rating_threshold" type="number" min="1" max="5" class="form-control" required />
                    </div>
                </div>
            </div>
            <div class="card-footer d-flex justify-content-end">
                <button class="btn btn-svtp" :disabled="form.processing">Save taxi settings</button>
            </div>
        </form>
    </AdminLayout>
</template>

<style scoped>
.card { border-radius: 1rem; border: 1px solid rgba(148, 163, 184, .12); background: #101827; color: #e2e8f0; }
.form-control, .form-select { background: rgba(15, 23, 42, .65); border-color: rgba(148, 163, 184, .25); color: #f8fafc; }
.form-control:focus, .form-select:focus { border-color: #f59e0b; box-shadow: 0 0 0 .2rem rgba(245, 158, 11, .18); }
.form-check-label { color: #e2e8f0; }
.form-text { color: #94a3b8; }
.btn.btn-outline-light { color: #f8fafc; border-color: rgba(148, 163, 184, .35); }
</style>
