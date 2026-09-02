<script setup>
import { computed, onMounted, reactive, ref } from 'vue';
import { router } from '@inertiajs/vue3';
import { appUrl } from '../appUrl';

const props = defineProps({ cities: { type: Array, default: () => [] } });

const pickupContainer = ref(null);
const destinationQuery = ref('');
const destinationOpen = ref(false);
const travellersOpen = ref(false);
const googleMapsKey = import.meta.env.VITE_GOOGLE_MAPS_API_KEY;

const form = reactive({
    city: '',
    pickup_address: '',
    pickup_place_id: '',
    pickup_lat: '',
    pickup_lng: '',
    date: '',
    adults: 2,
    children: 0,
});

const filteredCities = computed(() => {
    const query = destinationQuery.value.trim().toLowerCase();
    return query ? props.cities.filter((city) => city.name.toLowerCase().includes(query)) : props.cities;
});

const travellerLabel = computed(() => {
    const adults = `${form.adults} Adult${form.adults === 1 ? '' : 's'}`;
    return form.children ? `${adults}, ${form.children} ${form.children === 1 ? 'Child' : 'Children'}` : adults;
});

function selectDestination(city) {
    form.city = city.slug;
    destinationQuery.value = city.name;
    destinationOpen.value = false;
}

function adjustTraveller(type, amount) {
    const minimum = type === 'adults' ? 1 : 0;
    form[type] = Math.max(minimum, form[type] + amount);
}

function loadGoogleMaps() {
    if (window.google?.maps?.importLibrary) return Promise.resolve();

    return new Promise((resolve, reject) => {
        const existing = document.querySelector('script[data-google-maps]');
        if (existing) {
            existing.addEventListener('load', resolve, { once: true });
            existing.addEventListener('error', reject, { once: true });
            return;
        }

        const script = document.createElement('script');
        script.src = `https://maps.googleapis.com/maps/api/js?key=${googleMapsKey}&libraries=places&loading=async`;
        script.async = true;
        script.dataset.googleMaps = 'true';
        script.addEventListener('load', resolve, { once: true });
        script.addEventListener('error', reject, { once: true });
        document.head.appendChild(script);
    });
}

async function initializePickupAutocomplete() {
    if (!googleMapsKey || !pickupContainer.value) return;

    await loadGoogleMaps();
    const { PlaceAutocompleteElement } = await window.google.maps.importLibrary('places');
    const autocomplete = new PlaceAutocompleteElement({
        componentRestrictions: { country: 'in' },
        placeholder: 'Delhi, Agra...',
    });

    autocomplete.addEventListener('gmp-select', async ({ placePrediction }) => {
        const place = placePrediction.toPlace();
        await place.fetchFields({ fields: ['formattedAddress', 'location', 'id'] });

        form.pickup_address = place.formattedAddress || '';
        form.pickup_place_id = place.id || '';
        form.pickup_lat = place.location?.lat() ?? '';
        form.pickup_lng = place.location?.lng() ?? '';
    });

    pickupContainer.value.appendChild(autocomplete);
}

function search() {
    router.get(appUrl('/packages'), form);
}

onMounted(() => initializePickupAutocomplete().catch(() => {}));
</script>

<template>
    <form class="search-pill" @submit.prevent="search">
        <div class="row g-3 align-items-end">
            <div class="col-12 col-md-3">
                <label class="form-label d-block">Pickup From</label>
                <div v-if="googleMapsKey" ref="pickupContainer" class="places-autocomplete-host"></div>
                <input v-else v-model="form.pickup_address" class="form-control" placeholder="Delhi, Agra..." />
            </div>
            <div class="col-6 col-md-2 position-relative">
                <label class="form-label d-block">Destination</label>
                <input
                    v-model="destinationQuery"
                    class="form-control"
                    placeholder="Any destination"
                    autocomplete="off"
                    @focus="destinationOpen = true"
                    @input="form.city = ''; destinationOpen = true"
                />
                <div v-if="destinationOpen" class="search-dropdown">
                    <button v-for="city in filteredCities" :key="city.id" type="button" @click="selectDestination(city)">
                        <span>{{ city.name }}</span>
                        <small v-if="city.is_spiritual_hub">Popular</small>
                    </button>
                    <p v-if="!filteredCities.length" class="small text-muted p-2 mb-0">No destination found.</p>
                </div>
            </div>
            <div class="col-6 col-md-2">
                <label class="form-label d-block">Travel Date</label>
                <input v-model="form.date" type="date" :min="new Date().toISOString().slice(0, 10)" class="form-control" />
            </div>
            <div class="col-8 col-md-3 position-relative">
                <label class="form-label d-block">Travellers</label>
                <button type="button" class="form-control text-start" @click="travellersOpen = !travellersOpen">{{ travellerLabel }}</button>
                <div v-if="travellersOpen" class="search-dropdown traveller-dropdown">
                    <div class="traveller-row">
                        <span>Adults</span>
                        <div><button type="button" @click="adjustTraveller('adults', -1)">−</button><strong>{{ form.adults }}</strong><button type="button" @click="adjustTraveller('adults', 1)">+</button></div>
                    </div>
                    <div class="traveller-row">
                        <span>Children</span>
                        <div><button type="button" @click="adjustTraveller('children', -1)">−</button><strong>{{ form.children }}</strong><button type="button" @click="adjustTraveller('children', 1)">+</button></div>
                    </div>
                    <button type="button" class="btn btn-sm btn-svtp w-100 mt-2" @click="travellersOpen = false">Done</button>
                </div>
            </div>
            <div class="col-4 col-md-2">
                <button type="submit" class="btn btn-svtp w-100">Explore Tours</button>
            </div>
        </div>
    </form>
</template>
