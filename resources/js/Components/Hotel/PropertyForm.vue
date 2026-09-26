<script setup>
import { computed, onMounted, ref } from 'vue';
import { Link, useForm } from '@inertiajs/vue3';
import axios from 'axios';
import { appUrl } from '../../appUrl';
import CustomFieldInputs from './CustomFieldInputs.vue';
import RichTextEditor from '../Admin/RichTextEditor.vue';
import AiAssist from '../AiAssist.vue';

const props = defineProps({
    property: { type: Object, default: null },
    types: { type: Array, default: () => [] },
    amenities: { type: Array, default: () => [] },
    countries: { type: Array, default: () => [] },
    states: { type: Array, default: () => [] },
    vendors: { type: Array, default: () => [] },
    statuses: { type: Array, default: () => [] },
    submitUrl: { type: String, required: true },
    updateUrl: { type: String, default: '' },
    citiesUrl: { type: String, default: '' },
    destinationsUrl: { type: String, default: '' },
    showVendor: { type: Boolean, default: false },
    showStatus: { type: Boolean, default: false },
    showFeatured: { type: Boolean, default: false },
    customFields: { type: Array, default: () => [] },
    aiEndpoint: { type: String, required: true },
});

const editing = !!props.property;
const form = useForm({
    property_type_id: props.property?.property_type_id ?? '',
    name: props.property?.name ?? '',
    slug: props.property?.slug ?? '',
    short_description: props.property?.short_description ?? '',
    description: props.property?.description ?? '',
    is_featured: !!props.property?.is_featured,
    star_rating: props.property?.star_rating ?? '',
    address_line_1: props.property?.address_line_1 ?? '',
    address_line_2: props.property?.address_line_2 ?? '',
    city_id: props.property?.city_id ?? '',
    state_id: props.property?.state_id ?? '',
    country_id: props.property?.country_id ?? '',
    destination_id: props.property?.destination_id ?? '',
    country_code: props.property?.country_code ?? '',
    postal_code: props.property?.postal_code ?? '',
    latitude: props.property?.latitude ?? '',
    longitude: props.property?.longitude ?? '',
    phone: props.property?.phone ?? '',
    email: props.property?.email ?? '',
    website: props.property?.website ?? '',
    check_in_time: (props.property?.check_in_time ?? '').slice(0, 5),
    check_out_time: (props.property?.check_out_time ?? '').slice(0, 5),
    timezone: props.property?.timezone ?? '',
    currency: props.property?.currency ?? '',
    children_policy: props.property?.children_policy ?? '',
    pet_policy: props.property?.pet_policy ?? '',
    smoking_policy: props.property?.smoking_policy ?? '',
    check_in_instructions: props.property?.check_in_instructions ?? '',
    house_rules: props.property?.house_rules ?? '',
    meta_title: props.property?.meta_title ?? '',
    meta_description: props.property?.meta_description ?? '',
    amenity_ids: (props.property?.amenities ?? []).map(a => a.id),
    vendor_profile_id: props.property?.vendor_profile_id ?? '',
    status: props.property?.status ?? 'draft',
    publish_directly: false,
    custom_fields: Object.fromEntries((props.customFields ?? []).map(f => [f.id, f.value ?? (f.type === 'multiselect' ? [] : null)])),
});

const cities = ref([]);
const hotelAiContext = computed(() => ({
    name: form.name,
    type: props.types.find((item) => Number(item.id) === Number(form.property_type_id))?.name,
    city: cities.value.find((item) => Number(item.id) === Number(form.city_id))?.name ?? props.property?.city?.name,
}));
const citiesLoading = ref(false);
const destinations = ref([]);
const destinationsLoading = ref(false);

// States filtered client-side by the selected country (shared geography
// dependent select: Country → State → City → Destination/Area).
const visibleStates = computed(() => {
    if (!form.country_id) return props.states;
    return props.states.filter(s => !s.country_id || String(s.country_id) === String(form.country_id));
});

async function loadCities() {
    if (!props.citiesUrl || (!form.state_id && !form.country_id)) { cities.value = []; return; }
    citiesLoading.value = true;
    try {
        const { data } = await axios.get(props.citiesUrl, { params: { state_id: form.state_id || undefined, country_id: form.country_id || undefined } });
        cities.value = data;
    } catch { cities.value = []; }
    finally { citiesLoading.value = false; }
}

async function loadDestinations() {
    if (!props.destinationsUrl || (!form.city_id && !form.state_id && !form.country_id)) { destinations.value = []; return; }
    destinationsLoading.value = true;
    try {
        const { data } = await axios.get(props.destinationsUrl, { params: { city_id: form.city_id || undefined, state_id: form.state_id || undefined, country_id: form.country_id || undefined } });
        destinations.value = data;
    } catch { destinations.value = []; }
    finally { destinationsLoading.value = false; }
}

function onCountryChange() {
    form.state_id = '';
    form.city_id = '';
    form.destination_id = '';
    cities.value = [];
    destinations.value = [];
    loadCities();
    loadDestinations();
}

function onStateChange() {
    form.city_id = '';
    form.destination_id = '';
    destinations.value = [];
    loadCities();
    loadDestinations();
}

function onCityChange() {
    form.destination_id = '';
    loadDestinations();
}

onMounted(() => {
    if (form.state_id || form.country_id) loadCities();
    if (form.city_id || form.state_id || form.country_id) loadDestinations();
});

function submit() {
    form.transform((data) => {
        const payload = { ...data };
        ['star_rating', 'city_id', 'state_id', 'country_id', 'destination_id', 'property_type_id', 'vendor_profile_id'].forEach(k => {
            if (payload[k] === '') payload[k] = null;
        });
        if (!payload.slug) delete payload.slug;
        return payload;
    });
    if (editing && props.updateUrl) form.put(props.updateUrl);
    else form.post(props.submitUrl);
}

function applySeoDraft(draft) {
    form.meta_title = draft.title;
    form.meta_description = draft.description;
}
</script>
<template>
<form @submit.prevent="submit">
<div v-for="(error, key) in form.errors" :key="key" class="alert alert-danger py-1">{{ key }}: {{ error }}</div>

<h5 class="mt-1 mb-3">Basic information</h5>
<div class="row g-3">
<div class="col-md-6"><label class="form-label" for="pf-name">Property name *</label><input id="pf-name" v-model="form.name" required maxlength="150" class="form-control" /></div>
<div class="col-md-6"><label class="form-label" for="pf-slug">Slug (auto if blank)</label><input id="pf-slug" v-model="form.slug" maxlength="180" pattern="[a-z0-9]+(-[a-z0-9]+)*" class="form-control" /></div>
<div class="col-md-6"><label class="form-label" for="pf-type">Property type *</label><select id="pf-type" v-model="form.property_type_id" required class="form-select"><option value="">Choose type</option><option v-for="t in types" :key="t.id" :value="t.id">{{ t.name }}</option></select></div>
<div class="col-md-6"><label class="form-label" for="pf-star">Star classification</label><select id="pf-star" v-model="form.star_rating" class="form-select"><option value="">Unrated</option><option v-for="n in [1,2,3,4,5]" :key="n" :value="n">{{ n }} star{{ n > 1 ? 's' : '' }}</option></select></div>
<div class="col-12"><label class="form-label" for="pf-short">Short description</label><input id="pf-short" v-model="form.short_description" maxlength="500" class="form-control" /></div>
<div class="col-12"><label class="form-label">Full description</label><RichTextEditor v-model="form.description" /><div class="form-text">Use headings, lists, links and images. Unsafe HTML is cleaned on the server.</div><AiAssist content-type="hotel" field="description" :source="form.description" :context="hotelAiContext" :entity-id="property?.id" :endpoint="aiEndpoint" @apply="form.description = $event" /></div>
<div v-if="showVendor" class="col-md-6"><label class="form-label" for="pf-vendor">Owning vendor (blank = platform)</label><select id="pf-vendor" v-model="form.vendor_profile_id" class="form-select"><option value="">Platform-managed</option><option v-for="v in vendors" :key="v.id" :value="v.id">{{ v.business_name }}</option></select></div>
<div v-if="showStatus" class="col-md-6"><label class="form-label" for="pf-status">Status</label><select id="pf-status" v-model="form.status" class="form-select" disabled><option v-for="s in statuses" :key="s.value" :value="s.value">{{ s.label }}</option></select><div class="form-text">Status changes via publish / submit actions.</div></div>
<div v-if="showFeatured" class="col-md-6"><label class="form-check"><input v-model="form.is_featured" type="checkbox" class="form-check-input" /> Featured property</label></div>
</div>

<h5 class="mt-4 mb-3">Location</h5>
<div class="row g-3">
<div class="col-md-6"><label class="form-label" for="pf-addr1">Address line 1 *</label><input id="pf-addr1" v-model="form.address_line_1" required maxlength="255" class="form-control" /></div>
<div class="col-md-6"><label class="form-label" for="pf-addr2">Address line 2</label><input id="pf-addr2" v-model="form.address_line_2" maxlength="255" class="form-control" /></div>
<div class="col-md-3"><label class="form-label" for="pf-country">Country</label><select id="pf-country" v-model="form.country_id" class="form-select" @change="onCountryChange"><option value="">—</option><option v-for="c in countries" :key="c.id" :value="c.id">{{ c.name }}</option></select></div>
<div class="col-md-3"><label class="form-label" for="pf-state">State / region</label><select id="pf-state" v-model="form.state_id" class="form-select" @change="onStateChange"><option value="">—</option><option v-for="s in visibleStates" :key="s.id" :value="s.id">{{ s.name }}</option></select></div>
<div class="col-md-3"><label class="form-label" for="pf-city">City</label><select id="pf-city" v-model="form.city_id" class="form-select" @change="onCityChange"><option value="">—</option><option v-for="c in cities" :key="c.id" :value="c.id">{{ c.name }}{{ c.state ? ` (${c.state})` : '' }}</option></select><div v-if="citiesLoading" class="form-text">Loading cities…</div></div>
<div class="col-md-3"><label class="form-label" for="pf-dest">Destination / area (optional)</label><select id="pf-dest" v-model="form.destination_id" class="form-select"><option value="">—</option><option v-for="d in destinations" :key="d.id" :value="d.id">{{ d.name }}{{ d.city ? ` · ${d.city}` : '' }}</option></select><div v-if="destinationsLoading" class="form-text">Loading destinations…</div></div>
<div class="col-md-2"><label class="form-label" for="pf-cc">Country (ISO)</label><input id="pf-cc" v-model="form.country_code" maxlength="2" pattern="[A-Za-z]{2}" placeholder="US" class="form-control text-uppercase" /></div>
<div class="col-md-2"><label class="form-label" for="pf-post">Postal code</label><input id="pf-post" v-model="form.postal_code" maxlength="30" class="form-control" /></div>
<div class="col-md-3"><label class="form-label" for="pf-lat">Latitude</label><input id="pf-lat" v-model="form.latitude" type="number" step="0.0000001" min="-90" max="90" class="form-control" /></div>
<div class="col-md-3"><label class="form-label" for="pf-lng">Longitude</label><input id="pf-lng" v-model="form.longitude" type="number" step="0.0000001" min="-180" max="180" class="form-control" /></div>
</div>

<h5 class="mt-4 mb-3">Contact &amp; stay defaults</h5>
<div class="row g-3">
<div class="col-md-4"><label class="form-label" for="pf-phone">Phone</label><input id="pf-phone" v-model="form.phone" maxlength="30" class="form-control" /></div>
<div class="col-md-4"><label class="form-label" for="pf-email">Email</label><input id="pf-email" v-model="form.email" type="email" maxlength="150" class="form-control" /></div>
<div class="col-md-4"><label class="form-label" for="pf-web">Website</label><input id="pf-web" v-model="form.website" type="url" maxlength="255" placeholder="https://" class="form-control" /></div>
<div class="col-md-3"><label class="form-label" for="pf-in">Check-in</label><input id="pf-in" v-model="form.check_in_time" type="time" class="form-control" /></div>
<div class="col-md-3"><label class="form-label" for="pf-out">Check-out</label><input id="pf-out" v-model="form.check_out_time" type="time" class="form-control" /></div>
<div class="col-md-3"><label class="form-label" for="pf-tz">Timezone</label><input id="pf-tz" v-model="form.timezone" maxlength="60" placeholder="America/New_York" class="form-control" /></div>
<div class="col-md-3"><label class="form-label" for="pf-cur">Currency (ISO)</label><input id="pf-cur" v-model="form.currency" maxlength="3" pattern="[A-Za-z]{3}" placeholder="USD" class="form-control text-uppercase" /></div>
</div>

<h5 class="mt-4 mb-3">Amenities</h5>
<div class="row g-2">
<label v-for="a in amenities" :key="a.id" class="col-md-4 col-lg-3 form-check"><input v-model="form.amenity_ids" :value="a.id" type="checkbox" class="form-check-input" /> {{ a.name }} <span v-if="a.category" class="text-muted small">({{ a.category }})</span></label>
<p v-if="!amenities.length" class="text-muted">No active amenities defined yet.</p>
</div>

<h5 class="mt-4 mb-3">House policies</h5>
<div class="row g-3">
<div class="col-md-4"><label class="form-label" for="pf-child">Children policy</label><input id="pf-child" v-model="form.children_policy" maxlength="255" class="form-control" /></div>
<div class="col-md-4"><label class="form-label" for="pf-pet">Pet policy</label><input id="pf-pet" v-model="form.pet_policy" maxlength="255" class="form-control" /></div>
<div class="col-md-4"><label class="form-label" for="pf-smoke">Smoking policy</label><input id="pf-smoke" v-model="form.smoking_policy" maxlength="255" class="form-control" /></div>
<div class="col-md-6"><label class="form-label" for="pf-ci">Check-in instructions</label><textarea id="pf-ci" v-model="form.check_in_instructions" rows="3" maxlength="2000" class="form-control" /></div>
<div class="col-md-6"><label class="form-label" for="pf-hr">House rules</label><textarea id="pf-hr" v-model="form.house_rules" rows="3" maxlength="2000" class="form-control" /></div>
</div>

<h5 class="mt-4 mb-3">SEO</h5>
<div class="row g-3">
<div class="col-md-6"><label class="form-label" for="pf-meta-t">Meta title</label><input id="pf-meta-t" v-model="form.meta_title" maxlength="255" class="form-control" /></div>
<div class="col-md-6"><label class="form-label" for="pf-meta-d">Meta description</label><input id="pf-meta-d" v-model="form.meta_description" maxlength="500" class="form-control" /></div>
</div>
<AiAssist content-type="hotel" field="seo" mode="seo" :source="form.description" :context="hotelAiContext" :entity-id="property?.id" :endpoint="aiEndpoint" @apply="applySeoDraft" />

<div v-if="customFields.length" class="mt-4">
<h5 class="mb-3">Additional Information</h5>
<CustomFieldInputs :fields="customFields" v-model="form.custom_fields" prefix="pf-cf" />
</div>

<div class="d-flex gap-2 mt-4">
<button class="btn btn-svtp" :disabled="form.processing">{{ editing ? 'Save changes' : 'Create property' }}</button>
<slot name="actions" />
</div>
</form>
</template>
