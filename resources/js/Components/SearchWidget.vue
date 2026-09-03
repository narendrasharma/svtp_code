<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, reactive, ref } from 'vue';
import { router } from '@inertiajs/vue3';
import { appUrl } from '../appUrl';

const props = defineProps({ destinations: { type: Array, default: () => [] } });

const searchForm = ref(null);
const pickupContainer = ref(null);
const destinationInput = ref(null);
const destinationQuery = ref('');
const destinationOpen = ref(false);
const activeDestinationIndex = ref(-1);
const travellersOpen = ref(false);
const googleMapsKey = import.meta.env.VITE_GOOGLE_MAPS_API_KEY;

const form = reactive({
    destination_id: '',
    pickup_address: '',
    pickup_place_id: '',
    pickup_lat: '',
    pickup_lng: '',
    date: '',
    adults: 2,
    children: 0,
});

const internalDestinations = computed(() => Array.isArray(props.destinations)
    ? props.destinations
    : Object.values(props.destinations || {}));

const popularDestinations = computed(() => [...internalDestinations.value]
    .sort((first, second) => Number(second.city?.is_spiritual_hub) - Number(first.city?.is_spiritual_hub))
    .slice(0, 6));

const destinationSuggestions = computed(() => {
    const query = destinationQuery.value.trim().toLowerCase();

    if (!query) {
        return popularDestinations.value;
    }

    return internalDestinations.value.filter((destination) => [
        destination.name,
        destination.city?.name,
        destination.city?.state?.name,
    ].some((value) => value?.toLowerCase().includes(query)));
});

const travellerLabel = computed(() => {
    const adults = `${form.adults} Adult${form.adults === 1 ? '' : 's'}`;
    return form.children ? `${adults}, ${form.children} ${form.children === 1 ? 'Child' : 'Children'}` : adults;
});

function selectDestination(destination) {
    form.destination_id = destination.id;
    destinationQuery.value = destination.name;
    destinationOpen.value = false;
    activeDestinationIndex.value = -1;
}

function closeOpenMenus(event) {
    if (!searchForm.value?.contains(event.target)) {
        destinationOpen.value = false;
        travellersOpen.value = false;
    }
}

function openDestinationMenu() {
    destinationOpen.value = true;
    travellersOpen.value = false;
    activeDestinationIndex.value = -1;
}

function clearSelectedDestination() {
    form.destination_id = '';
    activeDestinationIndex.value = -1;
    openDestinationMenu();
}

function clearDestination() {
    destinationQuery.value = '';
    clearSelectedDestination();
    nextTick(() => destinationInput.value?.focus());
}

function handleDestinationKeydown(event) {
    if (!destinationSuggestions.value.length && ['ArrowDown', 'ArrowUp'].includes(event.key)) {
        event.preventDefault();
        openDestinationMenu();
        return;
    }

    if (event.key === 'ArrowDown') {
        event.preventDefault();
        openDestinationMenu();
        activeDestinationIndex.value = Math.min(activeDestinationIndex.value + 1, destinationSuggestions.value.length - 1);
        return;
    }

    if (event.key === 'ArrowUp') {
        event.preventDefault();
        activeDestinationIndex.value = Math.max(activeDestinationIndex.value - 1, 0);
        return;
    }

    if (event.key === 'Enter' && activeDestinationIndex.value >= 0) {
        event.preventDefault();
        selectDestination(destinationSuggestions.value[activeDestinationIndex.value]);
        return;
    }

    if (event.key === 'Escape') {
        destinationOpen.value = false;
        activeDestinationIndex.value = -1;
    }
}

function destinationContext(destination) {
    return [destination.city?.name, destination.city?.state?.name].filter(Boolean).join(', ');
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

onMounted(() => {
    document.addEventListener('pointerdown', closeOpenMenus);
    initializePickupAutocomplete().catch(() => {});
});

onBeforeUnmount(() => document.removeEventListener('pointerdown', closeOpenMenus));
</script>

<template>
    <form ref="searchForm" class="search-pill" @submit.prevent="search">
        <div class="row g-3 align-items-end">
            <div class="col-12 col-md-3">
                <label class="form-label d-block">Pickup From</label>
                <div v-if="googleMapsKey" ref="pickupContainer" class="places-autocomplete-host"></div>
                <input v-else v-model="form.pickup_address" class="form-control" placeholder="Delhi, Agra..." />
            </div>
            <div class="col-6 col-md-2 position-relative">
                <label class="form-label d-block">Destination</label>
                <div class="destination-input-wrapper">
                    <input
                        ref="destinationInput"
                        v-model="destinationQuery"
                        class="form-control"
                        placeholder="Any destination"
                        autocomplete="off"
                        role="combobox"
                        aria-autocomplete="list"
                        :aria-expanded="destinationOpen"
                        aria-controls="hero-destination-suggestions"
                        :aria-activedescendant="destinationSuggestions[activeDestinationIndex] ? `hero-destination-${destinationSuggestions[activeDestinationIndex].id}` : undefined"
                        @focus="openDestinationMenu"
                        @input="clearSelectedDestination"
                        @keydown="handleDestinationKeydown"
                    />
                    <button v-if="destinationQuery" type="button" class="destination-clear" aria-label="Clear destination" @click="clearDestination">
                        <i class="bi bi-x-lg"></i>
                    </button>
                </div>
                <div v-if="destinationOpen" id="hero-destination-suggestions" class="search-dropdown" role="listbox" aria-label="Destination suggestions">
                    <p v-if="!destinationQuery && destinationSuggestions.length" class="destination-suggestions-label">Popular destinations</p>
                    <button
                        v-for="(destination, index) in destinationSuggestions"
                        :id="`hero-destination-${destination.id}`"
                        :key="destination.id"
                        type="button"
                        role="option"
                        :aria-selected="index === activeDestinationIndex"
                        :class="{ 'is-active': index === activeDestinationIndex }"
                        @click="selectDestination(destination)"
                    >
                        <span>
                            <strong class="d-block">{{ destination.name }}</strong>
                            <small v-if="destinationContext(destination)" class="d-block">{{ destinationContext(destination) }}</small>
                        </span>
                    </button>
                    <p v-if="!destinationSuggestions.length" class="small text-muted p-2 mb-0">No destination found.</p>
                </div>
            </div>
            <div class="col-6 col-md-2">
                <label class="form-label d-block">Travel Date</label>
                <input v-model="form.date" type="date" :min="new Date().toISOString().slice(0, 10)" class="form-control" />
            </div>
            <div class="col-8 col-md-3 position-relative">
                <label class="form-label d-block">Travellers</label>
                <button type="button" class="form-control text-start" @click="destinationOpen = false; travellersOpen = !travellersOpen">{{ travellerLabel }}</button>
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

<style scoped>
.destination-input-wrapper { position: relative; }
.destination-input-wrapper .form-control { padding-right: 1.75rem; }
.destination-clear { position: absolute; right: 0; bottom: 0.3rem; border: 0; background: transparent; color: var(--gulal-deep); font-size: 0.72rem; }
.destination-suggestions-label { margin: 0; padding: 0.65rem 0.8rem 0.35rem; color: var(--gulal-deep); font-size: 0.72rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.04em; }
.search-dropdown > button.is-active { background: var(--cream-warm); }
</style>
