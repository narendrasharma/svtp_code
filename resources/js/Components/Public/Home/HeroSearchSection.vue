<script setup>
import { computed, onBeforeUnmount, ref } from 'vue';
import { appUrl } from '../../../appUrl';
import { useLocalization } from '../../../i18n';
import DateField from '../Search/DateField.vue';
import LocationField from '../Search/LocationField.vue';
import SearchButton from '../Search/SearchButton.vue';
import SearchTabs from '../Search/SearchTabs.vue';
import { mediaUrl } from '../homepage';

const props = defineProps({
    section: { type: Object, required: true },
});

const { t } = useLocalization();
const activeTab = ref(props.section.configuration?.default_tab || props.section.configuration?.tabs?.[0] || '');
const location = ref('');
const selectedLocation = ref(null);
const suggestions = ref([]);
const suggestionsOpen = ref(false);
const suggestionIndex = ref(-1);
const isLoading = ref(false);
const requestController = ref(null);
const form = ref({
    check_in: '',
    check_out: '',
    rooms: '1',
    adults: '2',
    children: '0',
    travel_date: '',
    kids: '0',
    pickup: '',
    drop: '',
    date: '',
    time: '',
    passengers: '1',
});
let debounceTimer;

const tabs = computed(() => (props.section.configuration?.tabs || []).map((key) => ({
    key,
    label: t(`common.${key}`, key),
})));

const activeContract = computed(() => props.section.configuration?.search_contracts?.[activeTab.value] || { fields: [] });
const heroMedia = computed(() => mediaUrl(props.section.configuration?.media_url));

function updateField(field, value) {
    form.value[field] = value;
}

function setTab(key) {
    activeTab.value = key;
    location.value = '';
    selectedLocation.value = null;
    suggestions.value = [];
    suggestionsOpen.value = false;
}

function onLocationInput(value) {
    location.value = value;
    selectedLocation.value = null;
    window.clearTimeout(debounceTimer);
    requestController.value?.abort();

    if (value.trim().length < 2) {
        suggestions.value = [];
        suggestionsOpen.value = false;
        return;
    }

    debounceTimer = window.setTimeout(async () => {
        const controller = new AbortController();
        requestController.value = controller;
        isLoading.value = true;

        try {
            const response = await fetch(`${appUrl('/discover/locations')}?q=${encodeURIComponent(value.trim())}&limit=8`, {
                headers: { Accept: 'application/json' },
                signal: controller.signal,
            });

            if (response.ok) {
                suggestions.value = (await response.json()).suggestions || [];
                suggestionsOpen.value = suggestions.value.length > 0;
                suggestionIndex.value = -1;
            }
        } catch (error) {
            if (error.name !== 'AbortError') suggestions.value = [];
        } finally {
            if (requestController.value === controller) isLoading.value = false;
        }
    }, 220);
}

function chooseSuggestion(suggestion) {
    location.value = suggestion.name;
    selectedLocation.value = suggestion;
    suggestionsOpen.value = false;
    suggestionIndex.value = -1;
}

function onLocationKeydown(event) {
    if (!suggestionsOpen.value || !suggestions.value.length) {
        if (event.key === 'Escape') suggestionsOpen.value = false;
        return;
    }

    if (event.key === 'ArrowDown') {
        event.preventDefault();
        suggestionIndex.value = (suggestionIndex.value + 1) % suggestions.value.length;
    } else if (event.key === 'ArrowUp') {
        event.preventDefault();
        suggestionIndex.value = suggestionIndex.value <= 0 ? suggestions.value.length - 1 : suggestionIndex.value - 1;
    } else if (event.key === 'Enter' && suggestionIndex.value >= 0) {
        event.preventDefault();
        chooseSuggestion(suggestions.value[suggestionIndex.value]);
    } else if (event.key === 'Escape') {
        suggestionsOpen.value = false;
    }
}

function locationParams(params) {
    if (!selectedLocation.value?.id) {
        params.set('q', location.value.trim());
        return;
    }

    params.set('location_type', selectedLocation.value.type);
    params.set('location_id', String(selectedLocation.value.id));
}

function submit() {
    if (!activeTab.value) return;

    const params = new URLSearchParams();

    if (activeTab.value === 'hotels') {
        locationParams(params);
        ['check_in', 'check_out', 'rooms', 'adults', 'children'].forEach((field) => {
            if (form.value[field] !== '') params.set(field, form.value[field]);
        });
        window.location.assign(`${appUrl('/search/hotels')}?${params.toString()}`);
        return;
    }

    if (activeTab.value === 'tours') {
        locationParams(params);
        [['travel_date', 'travel_date'], ['adults', 'adults'], ['kids', 'children']].forEach(([source, target]) => {
            if (form.value[source] !== '') params.set(target, form.value[source]);
        });
        window.location.assign(`${appUrl('/search/tours')}?${params.toString()}`);
        return;
    }

    const taxiParams = new URLSearchParams({
        pickup: form.value.pickup || '',
        drop: form.value.drop || '',
        date: form.value.date || '',
        time: form.value.time || '',
        passengers: form.value.passengers || '1',
    });
    window.location.assign(`${appUrl('/taxi')}?${taxiParams.toString()}`);
}

onBeforeUnmount(() => {
    window.clearTimeout(debounceTimer);
    requestController.value?.abort();
});
</script>

<template>
    <section class="homepage-hero" :class="{ 'homepage-hero--with-media': heroMedia }">
        <div v-if="heroMedia" class="homepage-hero__image" :style="{ backgroundImage: `url(${heroMedia})` }" aria-hidden="true"></div>
        <div class="homepage-hero__texture" aria-hidden="true"></div>
        <div class="public-container homepage-hero__inner">
            <div class="homepage-hero__copy">
                <span class="public-eyebrow homepage-hero__eyebrow">{{ t('common.travel_marketplace', 'Travel marketplace') }}</span>
                <h1>{{ section.title || t('common.find_your_journey', 'Find your next journey') }}</h1>
                <p v-if="section.subtitle">{{ section.subtitle }}</p>
            </div>

            <form class="homepage-search" :class="`homepage-search--${activeTab}`" @submit.prevent="submit">
                <SearchTabs :tabs="tabs" :model-value="activeTab" @update:model-value="setTab" />

                <div
                    v-if="activeTab"
                    :id="`homepage-panel-${activeTab}`"
                    class="homepage-search__fields"
                    :data-contract="activeContract.fields.join(',')"
                    role="tabpanel"
                    :aria-labelledby="`homepage-tab-${activeTab}`"
                >
                    <div v-if="activeTab === 'hotels' || activeTab === 'tours'" class="homepage-search__location">
                        <LocationField
                            :model-value="location"
                            :label="t('common.location', 'Location')"
                            :placeholder="t('common.city_or_destination', 'City or destination')"
                            :suggestions="suggestions"
                            :expanded="suggestionsOpen"
                            :loading="isLoading"
                            :active-index="suggestionIndex"
                            @update:model-value="onLocationInput"
                            @keydown="onLocationKeydown"
                            @select="chooseSuggestion"
                        />
                    </div>

                    <template v-if="activeTab === 'hotels'">
                        <DateField :model-value="form.check_in" :label="t('common.check_in', 'Check-in')" @update:model-value="updateField('check_in', $event)" />
                        <DateField :model-value="form.check_out" :label="t('common.check_out', 'Check-out')" @update:model-value="updateField('check_out', $event)" />
                        <label class="public-field"><span class="public-field__label">{{ t('common.rooms', 'Rooms') }}</span><input v-model="form.rooms" class="public-input" type="number" min="1" max="10"></label>
                        <label class="public-field"><span class="public-field__label">{{ t('common.adults', 'Adults') }}</span><input v-model="form.adults" class="public-input" type="number" min="1" max="20"></label>
                        <label class="public-field"><span class="public-field__label">{{ t('common.children', 'Children') }}</span><input v-model="form.children" class="public-input" type="number" min="0" max="20"></label>
                    </template>

                    <template v-else-if="activeTab === 'tours'">
                        <DateField :model-value="form.travel_date" :label="t('common.travel_date', 'Travel date')" @update:model-value="updateField('travel_date', $event)" />
                        <label class="public-field"><span class="public-field__label">{{ t('common.adults', 'Adults') }}</span><input v-model="form.adults" class="public-input" type="number" min="1" max="60"></label>
                        <label class="public-field"><span class="public-field__label">{{ t('common.kids', 'Kids') }}</span><input v-model="form.kids" class="public-input" type="number" min="0" max="60"></label>
                    </template>

                    <template v-else>
                        <label class="public-field"><span class="public-field__label">{{ t('common.pickup', 'Pickup') }}</span><input v-model="form.pickup" class="public-input" type="text" autocomplete="street-address"></label>
                        <label class="public-field"><span class="public-field__label">{{ t('common.drop', 'Drop') }}</span><input v-model="form.drop" class="public-input" type="text" autocomplete="street-address"></label>
                        <DateField :model-value="form.date" :label="t('common.date', 'Date')" @update:model-value="updateField('date', $event)" />
                        <label class="public-field"><span class="public-field__label">{{ t('common.time', 'Time') }}</span><input v-model="form.time" class="public-input" type="time"></label>
                        <label class="public-field"><span class="public-field__label">{{ t('common.passengers', 'Passengers') }}</span><input v-model="form.passengers" class="public-input" type="number" min="1" max="20"></label>
                    </template>

                    <SearchButton :label="t('common.search', 'Search')" />
                </div>
                <p v-else class="homepage-search__empty">{{ t('common.no_modules', 'Search is unavailable right now.') }}</p>
            </form>
        </div>
    </section>
</template>
