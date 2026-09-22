<script setup>
import AdminLayout from '../../../Layouts/AdminLayout.vue';
import { useForm } from '@inertiajs/vue3';
import { appUrl } from '../../../appUrl';
const props = defineProps({ settings: { type: Object, default: () => ({}) } });
function boolSetting(key, fallback = true) {
    const value = props.settings?.[key];
    if (value === undefined || value === null || value === '') return fallback;
    return ['1', 'true', 'yes', 'on'].includes(String(value).toLowerCase());
}
const form = useForm({
    hotel_vendor_can_create_properties: boolSetting('hotel.vendor_can_create_properties', true),
    hotel_require_property_approval: boolSetting('hotel.require_property_approval', true),
    hotel_default_currency: props.settings['hotel.default_currency'] ?? 'USD',
    hotel_default_timezone: props.settings['hotel.default_timezone'] ?? '',
    hotel_properties_per_page: Number(props.settings['hotel.properties_per_page'] ?? 12),
    hotel_show_contact_details: boolSetting('hotel.show_contact_details', true),
    hotel_rooms_show_size: boolSetting('hotel.rooms.show_size', true),
    hotel_rooms_show_bed_details: boolSetting('hotel.rooms.show_bed_details', true),
    hotel_rooms_units_enabled: boolSetting('hotel.rooms.units_enabled', true),
    hotel_inventory_max_bulk_days: Number(props.settings['hotel.inventory.max_bulk_days'] ?? 365),
    hotel_availability_max_stay_nights: Number(props.settings['hotel.availability.max_stay_nights'] ?? 30),
    hotel_availability_public_check_enabled: boolSetting('hotel.availability.public_check_enabled', true),
    hotel_pricing_max_bulk_days: Number(props.settings['hotel.pricing.max_bulk_days'] ?? 365),
    hotel_pricing_show_tax_breakdown: boolSetting('hotel.pricing.show_tax_breakdown', true),
    hotel_pricing_show_nightly_breakdown: boolSetting('hotel.pricing.show_nightly_breakdown', true),
    hotel_reviews_enabled: boolSetting('hotel.reviews.enabled', true),
    hotel_reviews_moderation_enabled: boolSetting('hotel.reviews.moderation_enabled', true),
    hotel_reviews_vendor_replies_enabled: boolSetting('hotel.reviews.vendor_replies_enabled', true),
    hotel_reviews_minimum_comment_length: Number(props.settings['hotel.reviews.minimum_comment_length'] ?? 20),
});
function submit() { form.post(appUrl('/admin/hotel/settings'), { preserveScroll: true }); }
</script>
<template><AdminLayout><div class="container-fluid py-3">
<h2 class="my-3">Hotel settings</h2>
<form class="card" @submit.prevent="submit"><div class="card-body">
<div v-for="(error, key) in form.errors" :key="key" class="text-danger">{{ error }}</div>
<div class="row g-3">
<div class="col-md-6"><div class="form-check form-switch"><input id="h-vendor-create" v-model="form.hotel_vendor_can_create_properties" type="checkbox" class="form-check-input" /><label class="form-check-label" for="h-vendor-create">Vendors can create properties</label></div></div>
<div class="col-md-6"><div class="form-check form-switch"><input id="h-approval" v-model="form.hotel_require_property_approval" type="checkbox" class="form-check-input" /><label class="form-check-label" for="h-approval">Require admin approval to publish</label></div><div class="form-text">Off = vendor submissions publish immediately.</div></div>
<div class="col-md-4"><label class="form-label small">Default currency (ISO)</label><input v-model="form.hotel_default_currency" maxlength="3" pattern="[A-Za-z]{3}" class="form-control text-uppercase" required /></div>
<div class="col-md-4"><label class="form-label small">Default timezone (blank = app default)</label><input v-model="form.hotel_default_timezone" maxlength="60" placeholder="America/New_York" class="form-control" /></div>
<div class="col-md-4"><label class="form-label small">Properties per page (6–48)</label><input v-model="form.hotel_properties_per_page" type="number" min="6" max="48" class="form-control" required /></div>
<div class="col-md-6"><div class="form-check form-switch"><input id="h-contact" v-model="form.hotel_show_contact_details" type="checkbox" class="form-check-input" /><label class="form-check-label" for="h-contact">Show contact details publicly</label></div><div class="form-text">Off = phone/email hidden on public property pages.</div></div>
<div class="col-md-4"><div class="form-check form-switch"><input id="h-room-size" v-model="form.hotel_rooms_show_size" type="checkbox" class="form-check-input" /><label class="form-check-label" for="h-room-size">Show room size publicly</label></div></div>
<div class="col-md-4"><div class="form-check form-switch"><input id="h-room-beds" v-model="form.hotel_rooms_show_bed_details" type="checkbox" class="form-check-input" /><label class="form-check-label" for="h-room-beds">Show bed details publicly</label></div></div>
<div class="col-md-4"><div class="form-check form-switch"><input id="h-room-units" v-model="form.hotel_rooms_units_enabled" type="checkbox" class="form-check-input" /><label class="form-check-label" for="h-room-units">Enable physical room units</label></div><div class="form-text">Off = aggregate inventory only.</div></div>
<div class="col-md-4"><label class="form-label small">Max bulk inventory days (1–730)</label><input v-model="form.hotel_inventory_max_bulk_days" type="number" min="1" max="730" class="form-control" required /></div>
<div class="col-md-4"><label class="form-label small">Max public stay nights (1–90)</label><input v-model="form.hotel_availability_max_stay_nights" type="number" min="1" max="90" class="form-control" required /></div>
<div class="col-md-6"><div class="form-check form-switch"><input id="h-avail-public" v-model="form.hotel_availability_public_check_enabled" type="checkbox" class="form-check-input" /><label class="form-check-label" for="h-avail-public">Enable public availability checks</label></div><div class="form-text">Off = availability endpoint returns 404.</div></div>
<div class="col-md-4"><label class="form-label small">Max bulk pricing days (1–730)</label><input v-model="form.hotel_pricing_max_bulk_days" type="number" min="1" max="730" class="form-control" required /></div>
<div class="col-md-4"><div class="form-check form-switch"><input id="h-price-tax" v-model="form.hotel_pricing_show_tax_breakdown" type="checkbox" class="form-check-input" /><label class="form-check-label" for="h-price-tax">Show tax breakdown publicly</label></div></div>
<div class="col-md-4"><div class="form-check form-switch"><input id="h-price-nightly" v-model="form.hotel_pricing_show_nightly_breakdown" type="checkbox" class="form-check-input" /><label class="form-check-label" for="h-price-nightly">Show nightly breakdown publicly</label></div></div>
</div>
<fieldset class="mt-4"><legend class="h5">Reviews &amp; ratings</legend><div class="row g-3">
<div class="col-md-6"><div class="form-check form-switch"><input id="hr-enabled" v-model="form.hotel_reviews_enabled" type="checkbox" class="form-check-input" /><label for="hr-enabled" class="form-check-label">Enable hotel reviews</label></div><div class="form-text">Controls customer submissions and public review/rating visibility.</div></div>
<div class="col-md-6"><div class="form-check form-switch"><input id="hr-moderation" v-model="form.hotel_reviews_moderation_enabled" type="checkbox" class="form-check-input" /><label for="hr-moderation" class="form-check-label">Moderate new reviews before publication</label></div><div class="form-text">Recommended. Off = new verified reviews publish immediately.</div></div>
<div class="col-md-6"><div class="form-check form-switch"><input id="hr-replies" v-model="form.hotel_reviews_vendor_replies_enabled" type="checkbox" class="form-check-input" /><label for="hr-replies" class="form-check-label">Allow official property responses</label></div></div>
<div class="col-md-6"><label for="hr-minimum" class="form-label">Minimum review length (10–500)</label><input id="hr-minimum" v-model.number="form.hotel_reviews_minimum_comment_length" type="number" min="10" max="500" class="form-control" required /></div>
</div></fieldset>
</div><div class="card-footer d-flex justify-content-end"><button class="btn btn-svtp" :disabled="form.processing">Save hotel settings</button></div></form>
</div></AdminLayout></template>
