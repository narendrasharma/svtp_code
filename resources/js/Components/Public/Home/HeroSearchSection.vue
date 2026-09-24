<script setup>
import { computed, ref } from 'vue';
import { appUrl } from '../../../appUrl';
import { useLocalization } from '../../../i18n';
import DateField from '../Search/DateField.vue';
import DateRangeField from '../Search/DateRangeField.vue';
import LocationField from '../Search/LocationField.vue';
import OccupancyField from '../Search/OccupancyField.vue';
import QuantitySelector from '../Search/QuantitySelector.vue';
import TimeField from '../Search/TimeField.vue';
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
}

function clearLocationSelection() {
    selectedLocation.value = null;
}

function chooseSuggestion(suggestion) {
    location.value = suggestion.name;
    selectedLocation.value = suggestion;
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
                            @update:model-value="location = $event"
                            @input="clearLocationSelection"
                            @select="chooseSuggestion"
                        />
                    </div>

                    <template v-if="activeTab === 'hotels'">
                        <DateRangeField :start="form.check_in" :end="form.check_out" :label="t('common.stay_dates', 'Check-in — Check-out')" id="hero-hotel-dates" @update:start="updateField('check_in', $event)" @update:end="updateField('check_out', $event)" />
                        <OccupancyField id="hero-hotel-guests" :adults="form.adults" :children="form.children" :rooms="form.rooms" :label="t('common.guests_and_rooms', 'Guests and rooms')" @update:adults="updateField('adults', $event)" @update:children="updateField('children', $event)" @update:rooms="updateField('rooms', $event)" />
                    </template>

                    <template v-else-if="activeTab === 'tours'">
                        <DateField :model-value="form.travel_date" :label="t('common.travel_date', 'Travel date')" @update:model-value="updateField('travel_date', $event)" />
                        <OccupancyField id="hero-tour-travellers" mode="tour" :adults="form.adults" :children="form.kids" :label="t('common.travellers', 'Travellers')" @update:adults="updateField('adults', $event)" @update:children="updateField('kids', $event)" />
                    </template>

                    <template v-else>
                        <LocationField id="hero-taxi-pickup" v-model="form.pickup" :label="t('common.pickup', 'Pickup')" placeholder="City, place or address" />
                        <LocationField id="hero-taxi-drop" v-model="form.drop" :label="t('common.drop', 'Drop')" placeholder="City, place or address" />
                        <DateField :model-value="form.date" :label="`${t('common.pickup', 'Pickup')} ${t('common.date', 'date')}`" @update:model-value="updateField('date', $event)" />
                        <TimeField v-model="form.time" :label="`${t('common.pickup', 'Pickup')} ${t('common.time', 'time')}`" id="hero-taxi-time" />
                        <QuantitySelector v-model="form.passengers" :label="t('common.passengers', 'Passengers')" id="hero-taxi-passengers" :min="1" :max="20" :decrease-label="t('common.decrease', 'Decrease')" :increase-label="t('common.increase', 'Increase')" />
                    </template>

                    <SearchButton :label="t('common.search', 'Search')" />
                </div>
                <p v-else class="homepage-search__empty">{{ t('common.no_modules', 'Search is unavailable right now.') }}</p>
            </form>
        </div>
    </section>
</template>
