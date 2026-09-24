<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, reactive, ref, watch } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import { appUrl } from '../../appUrl';
import { useLocalization } from '../../i18n';
import PublicLayout from '../../Layouts/PublicLayout.vue';
import SeoHead from '../../Components/SeoHead.vue';
import HotelSearchCard from '../../Components/Public/Hotels/HotelSearchCard.vue';
import EmptyState from '../../Components/Public/States/EmptyState.vue';
import ErrorState from '../../Components/Public/States/ErrorState.vue';
import DateRangeField from '../../Components/Public/Search/DateRangeField.vue';
import OccupancyField from '../../Components/Public/Search/OccupancyField.vue';

const props = defineProps({
    results: { type: Object, required: true },
    search: { type: Object, required: true },
    location: { type: Object, default: () => ({}) },
    seo: { type: Object, default: () => ({}) },
});

const { locale, t } = useLocalization();
const isSearchOpen = ref(false);
const isFiltersOpen = ref(false);
const closeFiltersButton = ref(null);
const hasNavigationError = ref(false);
const isNavigating = ref(false);
const searchForm = reactive({
    q: props.location?.label || props.search.q || '',
    check_in: props.search.check_in ?? '',
    check_out: props.search.check_out ?? '',
    rooms: props.search.rooms ?? 1,
    adults: props.search.adults ?? 2,
    children: props.search.children ?? 0,
});
const filterForm = reactive({
    property_type_id: props.search.property_type_id ?? '',
    min_rating: props.search.min_rating ?? '',
    min_price: props.search.min_price ?? '',
    max_price: props.search.max_price ?? '',
    amenities: [...(props.search.amenities ?? [])].map(String),
    meal_plan: props.search.meal_plan ?? '',
});
const sort = ref(props.results.sort ?? props.search.sort ?? 'recommended');
const facets = computed(() => props.results.facets ?? {});
const total = computed(() => Number(props.results.meta?.total ?? 0));
const currentPage = computed(() => Number(props.results.meta?.current_page ?? 1));
const lastPage = computed(() => Number(props.results.meta?.last_page ?? 1));
const hasResults = computed(() => Array.isArray(props.results.data) && props.results.data.length > 0);
const activeFilterCount = computed(() => {
    let count = 0;
    ['property_type_id', 'min_rating', 'min_price', 'max_price', 'meal_plan'].forEach((key) => {
        if (filterForm[key] !== '' && filterForm[key] !== null && filterForm[key] !== undefined) count += 1;
    });
    return count + filterForm.amenities.length;
});
const activeChips = computed(() => {
    const chips = [];
    const selectedType = facets.value.property_types?.find((item) => String(item.id) === String(filterForm.property_type_id));
    if (selectedType) chips.push({ key: 'property_type_id', label: selectedType.name });
    if (filterForm.min_rating) chips.push({ key: 'min_rating', label: String(filterForm.min_rating) + '+ ' + t('common.guest_rating', 'guest rating') });
    if (filterForm.min_price) chips.push({ key: 'min_price', label: t('common.from', 'From') + ' ' + filterForm.min_price + ' ' + props.search.price_currency });
    if (filterForm.max_price) chips.push({ key: 'max_price', label: t('common.to', 'to') + ' ' + filterForm.max_price + ' ' + props.search.price_currency });
    if (filterForm.meal_plan) chips.push({ key: 'meal_plan', label: mealLabel(filterForm.meal_plan) });
    filterForm.amenities.forEach((id) => {
        const amenity = facets.value.amenities?.find((item) => String(item.id) === String(id));
        if (amenity) chips.push({ key: 'amenity:' + id, label: amenity.name });
    });
    return chips;
});
const heading = computed(() => {
    if (props.location?.label) return t('common.stays_near', 'Stays near') + ' ' + props.location.label;
    if (props.search.q) return t('common.hotels_matching', 'Hotels matching') + ' “' + props.search.q + '”';
    return t('common.hotel_results', 'Hotels & stays');
});
const summaryLocation = computed(() => props.location?.label || props.search.q || t('common.anywhere', 'Anywhere'));
const summaryGuests = computed(() => {
    const adults = Number(props.search.adults ?? 2);
    const children = Number(props.search.children ?? 0);
    const rooms = Number(props.search.rooms ?? 1);
    const guests = adults + children;
    return guests + ' ' + (guests === 1 ? t('common.guest', 'guest') : t('common.guests', 'guests')) + ' · ' + rooms + ' ' + (rooms === 1 ? t('common.room', 'room') : t('common.rooms', 'rooms'));
});

function formatDate(value) {
    if (!value) return t('common.any_dates', 'Any dates');
    try {
        return new Intl.DateTimeFormat(locale.value || 'en', { day: 'numeric', month: 'short', year: 'numeric' }).format(new Date(value + 'T12:00:00'));
    } catch {
        return value;
    }
}

function mealLabel(value) {
    return String(value).replaceAll('_', ' ').replace(/\b\w/g, (character) => character.toUpperCase());
}

function baseParams() {
    const params = {};
    const unchanged = props.location?.id && searchForm.q.trim() === String(props.location.label || '').trim();
    if (unchanged) {
        params.location_type = props.location.type;
        params.location_id = props.location.id;
    } else if (searchForm.q.trim()) {
        params.q = searchForm.q.trim();
    }
    ['check_in', 'check_out', 'rooms', 'adults', 'children'].forEach((key) => {
        if (searchForm[key] !== '' && searchForm[key] !== null && searchForm[key] !== undefined) params[key] = searchForm[key];
    });
    return params;
}

function addFilters(params) {
    Object.entries({
        property_type_id: filterForm.property_type_id,
        min_rating: filterForm.min_rating,
        min_price: filterForm.min_price,
        max_price: filterForm.max_price,
        meal_plan: filterForm.meal_plan,
        price_currency: props.search.price_currency,
    }).forEach(([key, value]) => {
        if (value !== '' && value !== null && value !== undefined) params[key] = value;
    });
    if (filterForm.amenities.length) params.amenities = filterForm.amenities;
    return params;
}

function visit(params, options = {}) {
    hasNavigationError.value = false;
    isNavigating.value = true;
    router.get(appUrl('/search/hotels'), params, {
        preserveScroll: options.preserveScroll ?? true,
        preserveState: false,
        onFinish: () => { isNavigating.value = false; },
        onError: () => { hasNavigationError.value = true; isNavigating.value = false; },
    });
}

function submitSearch() {
    visit({ ...baseParams(), ...addFilters({}), sort: sort.value, page: 1 }, { preserveScroll: false });
    isSearchOpen.value = false;
}

function applyFilters() {
    visit({ ...baseParams(), ...addFilters({}), sort: sort.value, page: 1 });
    isFiltersOpen.value = false;
}

function clearFilters() {
    filterForm.property_type_id = '';
    filterForm.min_rating = '';
    filterForm.min_price = '';
    filterForm.max_price = '';
    filterForm.meal_plan = '';
    filterForm.amenities = [];
    applyFilters();
}

function removeChip(chip) {
    if (chip.key.startsWith('amenity:')) filterForm.amenities = filterForm.amenities.filter((id) => 'amenity:' + id !== chip.key);
    else filterForm[chip.key] = '';
    applyFilters();
}

function changeSort() {
    visit({ ...baseParams(), ...addFilters({}), sort: sort.value, page: 1 });
}

function queryString(params) {
    const query = new URLSearchParams();
    Object.entries(params).forEach(([key, value]) => {
        if (Array.isArray(value)) value.forEach((item) => query.append(key + '[]', item));
        else if (value !== '' && value !== null && value !== undefined) query.set(key, value);
    });
    return query.toString();
}

function pageUrl(page) {
    return appUrl('/search/hotels?' + queryString({ ...baseParams(), ...addFilters({}), sort: sort.value, page }));
}

function closeFilters() {
    isFiltersOpen.value = false;
}

function onKeydown(event) {
    if (event.key === 'Escape' && isFiltersOpen.value) closeFilters();
}

watch(isFiltersOpen, async (open) => {
    document.body.style.overflow = open ? 'hidden' : '';
    if (open) {
        await nextTick();
        closeFiltersButton.value?.focus();
    }
});

onMounted(() => window.addEventListener('keydown', onKeydown));
onBeforeUnmount(() => {
    window.removeEventListener('keydown', onKeydown);
    document.body.style.overflow = '';
});
</script>

<template>
    <PublicLayout main-class="hotel-search-page">
        <SeoHead :title="seo.title" :description="seo.description" :canonical="seo.canonical" :noindex="seo.noindex !== false" />
        <section class="hotel-search-hero">
            <div class="public-container">
                <div class="hotel-search-hero__intro">
                    <span class="public-eyebrow">{{ t('common.hotel_search', 'Hotel search') }}</span>
                    <h1>{{ heading }}</h1>
                    <p>{{ t('common.hotel_results_description', 'Compare considered stays with availability-aware search and clear pricing.') }}</p>
                </div>
                <form class="hotel-search-summary" :class="{ 'is-open': isSearchOpen }" @submit.prevent="submitSearch">
                    <button type="button" class="hotel-search-summary__mobile-trigger" :aria-expanded="isSearchOpen" @click="isSearchOpen = !isSearchOpen">
                        <span><i class="bi bi-sliders2" aria-hidden="true"></i> {{ t('common.modify_search', 'Modify search') }}</span><i class="bi" :class="isSearchOpen ? 'bi-chevron-up' : 'bi-chevron-down'" aria-hidden="true"></i>
                    </button>
                    <div class="hotel-search-summary__context">
                        <span><i class="bi bi-geo-alt" aria-hidden="true"></i><strong>{{ summaryLocation }}</strong></span>
                        <span><i class="bi bi-calendar3" aria-hidden="true"></i>{{ formatDate(search.check_in) }} – {{ formatDate(search.check_out) }}</span>
                        <span><i class="bi bi-people" aria-hidden="true"></i>{{ summaryGuests }}</span>
                    </div>
                    <div class="hotel-search-summary__fields">
                        <label class="public-field hotel-search-summary__location-field"><span class="public-field__label">{{ t('common.location', 'Location') }}</span><input v-model="searchForm.q" class="public-input" :placeholder="t('common.city_or_destination', 'City or destination')" autocomplete="off"></label>
                        <DateRangeField v-model:start="searchForm.check_in" v-model:end="searchForm.check_out" :label="t('common.stay_dates', 'Check-in — Check-out')" id="hotel-stay-dates" />
                        <OccupancyField id="hotel-guests" v-model:adults="searchForm.adults" v-model:children="searchForm.children" v-model:rooms="searchForm.rooms" :label="t('common.guests_and_rooms', 'Guests and rooms')" />
                        <button type="submit" class="public-button public-button--primary">{{ t('common.update_search', 'Update search') }}</button>
                    </div>
                </form>
            </div>
        </section>
        <main class="public-section hotel-search-results">
            <div class="public-container">
                <div class="hotel-search-results__toolbar">
                    <div><span class="public-eyebrow">{{ t('common.curated_stays', 'Curated stays') }}</span><p class="hotel-search-results__count" aria-live="polite">{{ total }} {{ total === 1 ? t('common.property', 'property') : t('common.properties', 'properties') }}</p></div>
                    <div class="hotel-search-results__controls">
                        <button type="button" class="public-button public-button--outline public-button--sm hotel-search-filter-trigger" :aria-expanded="isFiltersOpen" @click="isFiltersOpen = true"><i class="bi bi-sliders2" aria-hidden="true"></i>{{ t('common.filters', 'Filters') }}<span v-if="activeFilterCount" class="hotel-search-count-badge">{{ activeFilterCount }}</span></button>
                        <label class="hotel-search-sort"><span>{{ t('common.sort', 'Sort') }}</span><select v-model="sort" class="public-select-input" @change="changeSort"><option value="recommended">{{ t('common.recommended', 'Recommended') }}</option><option value="price_asc">{{ t('common.price_low_high', 'Price: low to high') }}</option><option value="price_desc">{{ t('common.price_high_low', 'Price: high to low') }}</option><option value="rating_desc">{{ t('common.guest_rating_high', 'Guest rating') }}</option></select></label>
                    </div>
                </div>
                <div v-if="activeChips.length" class="hotel-search-chips" aria-label="Active filters">
                    <span v-for="chip in activeChips" :key="chip.key" class="public-chip public-chip--neutral">{{ chip.label }}<button type="button" :aria-label="t('common.remove', 'Remove') + ' ' + chip.label" @click="removeChip(chip)"><i class="bi bi-x" aria-hidden="true"></i></button></span>
                    <button type="button" class="hotel-search-clear-link" @click="clearFilters">{{ t('common.clear_all', 'Clear all') }}</button>
                </div>
                <div class="hotel-search-layout">
                    <aside class="hotel-filter-panel" :class="{ 'is-open': isFiltersOpen }" :aria-hidden="!isFiltersOpen && undefined" aria-label="Hotel filters">
                        <div class="hotel-filter-panel__backdrop" @click="closeFilters"></div>
                        <div class="hotel-filter-panel__surface" role="dialog" :aria-modal="isFiltersOpen || undefined" :aria-label="t('common.filters', 'Filters')">
                            <div class="hotel-filter-panel__header"><div><span class="public-eyebrow">{{ t('common.refine', 'Refine') }}</span><h2>{{ t('common.filters', 'Filters') }}</h2></div><button ref="closeFiltersButton" type="button" class="public-icon-button hotel-filter-panel__close" :aria-label="t('common.close', 'Close')" @click="closeFilters"><i class="bi bi-x-lg" aria-hidden="true"></i></button></div>
                            <div class="hotel-filter-panel__body">
                                <fieldset v-if="facets.property_types?.length" class="hotel-filter-group"><legend>{{ t('common.property_type', 'Property type') }}</legend><label v-for="type in facets.property_types" :key="type.id" class="hotel-filter-option"><input v-model="filterForm.property_type_id" type="radio" name="property_type_id" :value="String(type.id)"><span>{{ type.name }}</span><small>{{ type.count }}</small></label></fieldset>
                                <fieldset v-if="facets.rating_bands?.length" class="hotel-filter-group"><legend>{{ t('common.guest_rating', 'Guest rating') }}</legend><label v-for="band in facets.rating_bands" :key="band.min" class="hotel-filter-option"><input v-model="filterForm.min_rating" type="radio" name="min_rating" :value="String(band.min)"><span>{{ band.min }}+</span><small>{{ band.count }}</small></label></fieldset>
                                <fieldset class="hotel-filter-group"><legend>{{ t('common.price_range', 'Price range') }}</legend><p class="hotel-filter-note">{{ t('common.price_filter_note', 'Uses the rate currency returned by the search contract.') }} ({{ search.price_currency }})</p><div class="hotel-filter-price-fields"><label class="public-field"><span class="public-field__label">{{ t('common.minimum', 'Minimum') }}</span><input v-model="filterForm.min_price" class="public-input" type="number" min="0" step="0.01"></label><label class="public-field"><span class="public-field__label">{{ t('common.maximum', 'Maximum') }}</span><input v-model="filterForm.max_price" class="public-input" type="number" min="0" step="0.01"></label></div></fieldset>
                                <fieldset v-if="facets.amenities?.length" class="hotel-filter-group"><legend>{{ t('common.amenities', 'Amenities') }}</legend><label v-for="amenity in facets.amenities.slice(0, 8)" :key="amenity.id" class="hotel-filter-option"><input v-model="filterForm.amenities" type="checkbox" :value="String(amenity.id)"><span>{{ amenity.name }}</span><small>{{ amenity.count }}</small></label></fieldset>
                                <fieldset v-if="facets.meal_plans?.length" class="hotel-filter-group"><legend>{{ t('common.meal_plan', 'Meal plan') }}</legend><label v-for="meal in facets.meal_plans" :key="meal.value" class="hotel-filter-option"><input v-model="filterForm.meal_plan" type="radio" name="meal_plan" :value="meal.value"><span>{{ mealLabel(meal.value) }}</span><small>{{ meal.count }}</small></label></fieldset>
                            </div>
                            <div class="hotel-filter-panel__footer"><button type="button" class="public-button public-button--outline" @click="clearFilters">{{ t('common.clear_all', 'Clear all') }}</button><button type="button" class="public-button public-button--primary" @click="applyFilters">{{ t('common.apply_filters', 'Apply filters') }}</button></div>
                        </div>
                    </aside>
                    <section class="hotel-search-list" :aria-busy="isNavigating">
                        <div v-if="isNavigating" class="hotel-search-loading" role="status">{{ t('common.loading_results', 'Updating stays…') }}</div>
                        <ErrorState v-if="hasNavigationError" :title="t('common.search_error', 'Search could not be updated')" :description="t('common.try_again', 'Please try again in a moment.')" />
                        <template v-else-if="hasResults">
                            <HotelSearchCard v-for="hotel in results.data" :key="hotel.id" :hotel="hotel" :search="search" />
                            <nav v-if="lastPage > 1" class="hotel-search-pagination" :aria-label="t('common.pagination', 'Pagination')">
                                <Link v-if="currentPage > 1" :href="pageUrl(currentPage - 1)" class="public-button public-button--outline public-button--sm"><i class="bi bi-arrow-left" data-dir-icon="arrow" aria-hidden="true"></i>{{ t('common.previous', 'Previous') }}</Link>
                                <div class="hotel-search-pagination__pages"><Link v-for="page in lastPage" :key="page" :href="pageUrl(page)" class="hotel-search-pagination__page" :class="{ 'is-current': page === currentPage }" :aria-current="page === currentPage ? 'page' : undefined">{{ page }}</Link></div>
                                <Link v-if="currentPage < lastPage" :href="pageUrl(currentPage + 1)" class="public-button public-button--outline public-button--sm">{{ t('common.next', 'Next') }}<i class="bi bi-arrow-right" data-dir-icon="arrow" aria-hidden="true"></i></Link>
                            </nav>
                        </template>
                        <EmptyState v-else :title="t('common.no_hotels_found', 'No stays found')" :description="t('common.no_hotels_found_description', 'Try adjusting your dates, location, or filters to discover another stay.')"><button v-if="activeFilterCount" type="button" class="public-button public-button--primary" @click="clearFilters">{{ t('common.clear_filters', 'Clear filters') }}</button><button v-else type="button" class="public-button public-button--outline" @click="isSearchOpen = true">{{ t('common.modify_search', 'Modify search') }}</button></EmptyState>
                    </section>
                </div>
            </div>
        </main>
    </PublicLayout>
</template>
