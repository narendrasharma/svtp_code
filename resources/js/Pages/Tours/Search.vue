<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, reactive, ref, watch } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import { appUrl } from '../../appUrl';
import { useLocalization } from '../../i18n';
import PublicLayout from '../../Layouts/PublicLayout.vue';
import SeoHead from '../../Components/SeoHead.vue';
import EmptyState from '../../Components/Public/States/EmptyState.vue';
import ErrorState from '../../Components/Public/States/ErrorState.vue';
import TourSearchCard from '../../Components/Public/Tours/TourSearchCard.vue';

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
    travel_date: props.search.travel_date ?? '',
    adults: props.search.adults ?? 1,
    children: props.search.children ?? 0,
});
const filterForm = reactive({
    category_id: props.search.category_id ?? '',
    min_duration: props.search.min_duration ?? '',
    max_duration: props.search.max_duration ?? '',
    min_price: props.search.min_price ?? '',
    max_price: props.search.max_price ?? '',
    tags: [...(props.search.tags ?? [])].map(String),
    featured: !!props.search.featured,
    min_rating: props.search.min_rating ?? '',
});
const sort = ref(props.results.sort ?? props.search.sort ?? 'recommended');
const facets = computed(() => props.results.facets ?? {});
const tours = computed(() => Array.isArray(props.results.data) ? props.results.data : []);
const total = computed(() => Number(props.results.meta?.total ?? 0));
const currentPage = computed(() => Number(props.results.meta?.current_page ?? 1));
const lastPage = computed(() => Number(props.results.meta?.last_page ?? 1));
const activeFilterCount = computed(() => {
    let count = 0;
    ['category_id', 'min_duration', 'max_duration', 'min_price', 'max_price', 'min_rating'].forEach((key) => {
        if (filterForm[key] !== '' && filterForm[key] !== null && filterForm[key] !== undefined) count += 1;
    });
    return count + filterForm.tags.length + (filterForm.featured ? 1 : 0);
});
const activeChips = computed(() => {
    const chips = [];
    const category = facets.value.categories?.find((item) => String(item.id) === String(filterForm.category_id));
    if (category) chips.push({ key: 'category_id', label: category.name });
    if (filterForm.min_duration || filterForm.max_duration) {
        chips.push({ key: 'duration', label: durationFacetLabel(filterForm.min_duration, filterForm.max_duration) });
    }
    if (filterForm.min_price) chips.push({ key: 'min_price', label: `${t('common.from', 'From')} ${filterForm.min_price}` });
    if (filterForm.max_price) chips.push({ key: 'max_price', label: `${t('common.to', 'To')} ${filterForm.max_price}` });
    if (filterForm.min_rating) chips.push({ key: 'min_rating', label: `${filterForm.min_rating}+ ${t('common.guest_rating', 'guest rating')}` });
    if (filterForm.featured) chips.push({ key: 'featured', label: t('common.featured', 'Featured') });
    filterForm.tags.forEach((slug) => {
        const tag = facets.value.tags?.find((item) => item.slug === slug);
        if (tag) chips.push({ key: `tag:${slug}`, label: tag.name });
    });
    return chips;
});
const heading = computed(() => {
    if (props.location?.label) return t('common.tours_in', 'Tours in').replace(':location', props.location.label);
    if (props.search.q) return `${t('common.tours_matching', 'Tours matching')} “${props.search.q}”`;
    return t('common.tour_results', 'Tours and experiences');
});
const summaryLocation = computed(() => props.location?.label || props.search.q || t('common.anywhere', 'Anywhere'));
const summaryParty = computed(() => {
    const adults = Number(props.search.adults ?? 1);
    const children = Number(props.search.children ?? 0);
    const people = adults + children;
    return `${people} ${people === 1 ? t('common.traveller', 'traveller') : t('common.travellers', 'travellers')}`;
});
const ratingOptions = [4, 3];

function formatDate(value) {
    if (!value) return t('common.any_dates', 'Any dates');
    try {
        return new Intl.DateTimeFormat(locale.value || 'en', { day: 'numeric', month: 'short', year: 'numeric' }).format(new Date(`${value}T12:00:00`));
    } catch {
        return value;
    }
}

function durationFacetLabel(min, max) {
    if (String(min) === '1' && String(max) === '3') return t('common.duration_short', '1–3 days');
    if (String(min) === '4' && String(max) === '7') return t('common.duration_medium', '4–7 days');
    if (String(min) === '8') return t('common.duration_long', '8+ days');
    if (min && max) return `${min}–${max} ${t('common.days', 'days')}`;
    if (min) return `${t('common.from', 'From')} ${min} ${t('common.days', 'days')}`;
    return `${t('common.to', 'To')} ${max} ${t('common.days', 'days')}`;
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
    ['travel_date', 'adults', 'children'].forEach((key) => {
        if (searchForm[key] !== '' && searchForm[key] !== null && searchForm[key] !== undefined) params[key] = searchForm[key];
    });
    return params;
}

function addFilters(params) {
    Object.entries({
        category_id: filterForm.category_id,
        min_duration: filterForm.min_duration,
        max_duration: filterForm.max_duration,
        min_price: filterForm.min_price,
        max_price: filterForm.max_price,
        featured: filterForm.featured ? 1 : '',
        min_rating: filterForm.min_rating,
    }).forEach(([key, value]) => {
        if (value !== '' && value !== null && value !== undefined) params[key] = value;
    });
    if (filterForm.tags.length) params.tags = filterForm.tags;
    return params;
}

function visit(params, options = {}) {
    hasNavigationError.value = false;
    isNavigating.value = true;
    router.get(appUrl('/search/tours'), params, {
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
    filterForm.category_id = '';
    filterForm.min_duration = '';
    filterForm.max_duration = '';
    filterForm.min_price = '';
    filterForm.max_price = '';
    filterForm.tags = [];
    filterForm.featured = false;
    filterForm.min_rating = '';
    applyFilters();
}

function removeChip(chip) {
    if (chip.key === 'duration') {
        filterForm.min_duration = '';
        filterForm.max_duration = '';
    } else if (chip.key.startsWith('tag:')) {
        filterForm.tags = filterForm.tags.filter((tag) => `tag:${tag}` !== chip.key);
    } else {
        filterForm[chip.key] = chip.key === 'featured' ? false : '';
    }
    applyFilters();
}

function changeSort() {
    visit({ ...baseParams(), ...addFilters({}), sort: sort.value, page: 1 });
}

function queryString(params) {
    const query = new URLSearchParams();
    Object.entries(params).forEach(([key, value]) => {
        if (Array.isArray(value)) value.forEach((item) => query.append(`${key}[]`, item));
        else if (value !== '' && value !== null && value !== undefined) query.set(key, value);
    });
    return query.toString();
}

function pageUrl(page) {
    return appUrl(`/search/tours?${queryString({ ...baseParams(), ...addFilters({}), sort: sort.value, page })}`);
}

function closeFilters() {
    isFiltersOpen.value = false;
}

function onKeydown(event) {
    if (event.key === 'Escape' && isFiltersOpen.value) closeFilters();
}

onMounted(() => window.addEventListener('keydown', onKeydown));
watch(isFiltersOpen, (open) => {
    document.body.style.overflow = open ? 'hidden' : '';
});
onBeforeUnmount(() => {
    window.removeEventListener('keydown', onKeydown);
    document.body.style.overflow = '';
});

async function openFilters() {
    isFiltersOpen.value = true;
    await nextTick();
    closeFiltersButton.value?.focus();
}
</script>

<template>
    <PublicLayout main-class="tour-search-page">
        <SeoHead :title="seo.title" :description="seo.description" :canonical="seo.canonical" :noindex="seo.noindex !== false" />
        <section class="tour-search-hero">
            <div class="public-container">
                <div class="tour-search-hero__intro">
                    <span class="public-eyebrow">{{ t('common.experiences', 'Experiences') }}</span>
                    <h1>{{ heading }}</h1>
                    <p>{{ t('common.tour_results_description', 'Find thoughtful journeys, day experiences, and destination-led escapes with clear starting prices.') }}</p>
                </div>
                <form class="tour-search-summary" :class="{ 'is-open': isSearchOpen }" @submit.prevent="submitSearch">
                    <button type="button" class="tour-search-summary__mobile-trigger" :aria-expanded="isSearchOpen" @click="isSearchOpen = !isSearchOpen">
                        <span><i class="bi bi-sliders2" aria-hidden="true"></i> {{ t('common.modify_search', 'Modify search') }}</span><i class="bi" :class="isSearchOpen ? 'bi-chevron-up' : 'bi-chevron-down'" aria-hidden="true"></i>
                    </button>
                    <div class="tour-search-summary__context">
                        <span><i class="bi bi-geo-alt" aria-hidden="true"></i><strong>{{ summaryLocation }}</strong></span>
                        <span><i class="bi bi-calendar3" aria-hidden="true"></i>{{ formatDate(search.travel_date) }}</span>
                        <span><i class="bi bi-people" aria-hidden="true"></i>{{ summaryParty }}</span>
                    </div>
                    <div class="tour-search-summary__fields">
                        <label class="public-field tour-search-summary__location-field"><span class="public-field__label">{{ t('common.destination', 'Destination') }}</span><input v-model="searchForm.q" class="public-input" :placeholder="t('common.city_or_destination', 'City or destination')" autocomplete="off"></label>
                        <label class="public-field"><span class="public-field__label">{{ t('common.travel_date', 'Travel date') }}</span><input v-model="searchForm.travel_date" class="public-input" type="date"></label>
                        <label class="public-field"><span class="public-field__label">{{ t('common.adults', 'Adults') }}</span><input v-model="searchForm.adults" class="public-input" type="number" min="1" max="60"></label>
                        <label class="public-field"><span class="public-field__label">{{ t('common.kids', 'Kids') }}</span><input v-model="searchForm.children" class="public-input" type="number" min="0" max="60"></label>
                        <button type="submit" class="public-button public-button--primary">{{ t('common.update_search', 'Update search') }}</button>
                    </div>
                </form>
            </div>
        </section>

        <main class="public-section tour-search-results">
            <div class="public-container public-container--wide">
                <div class="tour-search-results__toolbar">
                    <div><span class="public-eyebrow">{{ t('common.curated_experiences', 'Curated experiences') }}</span><p class="tour-search-results__count" aria-live="polite">{{ total }} {{ total === 1 ? t('common.tour', 'tour') : t('common.tours', 'tours') }}</p></div>
                    <div class="tour-search-results__controls">
                        <button type="button" class="public-button public-button--outline public-button--sm tour-search-filter-trigger" :aria-expanded="isFiltersOpen" @click="openFilters"><i class="bi bi-sliders2" aria-hidden="true"></i>{{ t('common.filters', 'Filters') }}<span v-if="activeFilterCount" class="tour-search-count-badge">{{ activeFilterCount }}</span></button>
                        <label class="tour-search-sort"><span>{{ t('common.sort', 'Sort') }}</span><select v-model="sort" class="public-select-input" @change="changeSort"><option value="recommended">{{ t('common.recommended', 'Recommended') }}</option><option value="price_asc">{{ t('common.price_low_high', 'Price: low to high') }}</option><option value="price_desc">{{ t('common.price_high_low', 'Price: high to low') }}</option><option value="duration_asc">{{ t('common.duration_shortest', 'Shortest duration') }}</option></select></label>
                    </div>
                </div>

                <div v-if="activeChips.length" class="tour-search-chips" :aria-label="t('common.active_filters', 'Active filters')">
                    <span v-for="chip in activeChips" :key="chip.key" class="public-chip public-chip--neutral">{{ chip.label }}<button type="button" :aria-label="t('common.remove', 'Remove') + ' ' + chip.label" @click="removeChip(chip)"><i class="bi bi-x" aria-hidden="true"></i></button></span>
                    <button type="button" class="tour-search-clear-link" @click="clearFilters">{{ t('common.clear_all', 'Clear all') }}</button>
                </div>

                <div class="tour-search-layout">
                    <aside class="tour-filter-panel" :class="{ 'is-open': isFiltersOpen }" :aria-hidden="!isFiltersOpen && undefined" :aria-label="t('common.tour_filters', 'Tour filters')">
                        <div class="tour-filter-panel__backdrop" @click="closeFilters"></div>
                        <div class="tour-filter-panel__surface" role="dialog" :aria-modal="isFiltersOpen || undefined" :aria-label="t('common.filters', 'Filters')">
                            <div class="tour-filter-panel__header"><div><span class="public-eyebrow">{{ t('common.refine', 'Refine') }}</span><h2>{{ t('common.filters', 'Filters') }}</h2></div><button ref="closeFiltersButton" type="button" class="public-icon-button tour-filter-panel__close" :aria-label="t('common.close', 'Close')" @click="closeFilters"><i class="bi bi-x-lg" aria-hidden="true"></i></button></div>
                            <div class="tour-filter-panel__body">
                                <fieldset v-if="facets.categories?.length" class="tour-filter-group"><legend>{{ t('common.category', 'Category') }}</legend><label v-for="category in facets.categories" :key="category.id" class="tour-filter-option"><input v-model="filterForm.category_id" type="radio" name="tour_category" :value="String(category.id)"><span>{{ category.name }}</span><small>{{ category.count }}</small></label></fieldset>
                                <fieldset v-if="facets.duration_bands?.length" class="tour-filter-group"><legend>{{ t('common.duration', 'Duration') }}</legend><label v-for="band in facets.duration_bands" :key="`${band.min}-${band.max}`" class="tour-filter-option"><input v-model="filterForm.min_duration" type="radio" name="tour_duration" :value="String(band.min)" @change="filterForm.max_duration = band.max ? String(band.max) : ''"><span>{{ durationFacetLabel(band.min, band.max) }}</span><small>{{ band.count }}</small></label></fieldset>
                                <fieldset v-if="facets.tags?.length" class="tour-filter-group"><legend>{{ t('common.tags', 'Themes') }}</legend><label v-for="tag in facets.tags" :key="tag.id" class="tour-filter-option"><input v-model="filterForm.tags" type="checkbox" :value="tag.slug"><span>{{ tag.name }}</span><small>{{ tag.count }}</small></label></fieldset>
                                <fieldset class="tour-filter-group"><legend>{{ t('common.price_range', 'Price range') }}</legend><div class="tour-filter-price-fields"><label class="public-field"><span class="public-field__label">{{ t('common.minimum', 'Minimum') }}</span><input v-model="filterForm.min_price" class="public-input" type="number" min="0" inputmode="decimal"></label><label class="public-field"><span class="public-field__label">{{ t('common.maximum', 'Maximum') }}</span><input v-model="filterForm.max_price" class="public-input" type="number" min="0" inputmode="decimal"></label></div><p class="tour-filter-note">{{ t('common.tour_price_filter_note', 'Uses the tour source currency returned by search.') }}</p></fieldset>
                                <fieldset class="tour-filter-group"><legend>{{ t('common.guest_rating', 'Guest rating') }}</legend><label v-for="rating in ratingOptions" :key="rating" class="tour-filter-option"><input v-model="filterForm.min_rating" type="radio" name="tour_rating" :value="String(rating)"><span>{{ rating }}+ {{ t('common.stars', 'stars') }}</span></label></fieldset>
                                <label class="tour-filter-option tour-filter-option--featured"><input v-model="filterForm.featured" type="checkbox"><span>{{ t('common.featured_only', 'Featured experiences only') }}</span></label>
                            </div>
                            <div class="tour-filter-panel__footer"><button type="button" class="public-button public-button--outline" @click="clearFilters">{{ t('common.clear_filters', 'Clear filters') }}</button><button type="button" class="public-button public-button--primary" @click="applyFilters">{{ t('common.apply_filters', 'Apply filters') }}</button></div>
                        </div>
                    </aside>

                    <section class="tour-search-list" aria-live="polite">
                        <div v-if="isNavigating" class="tour-search-loading" role="status"><span class="visually-hidden">{{ t('common.loading_results', 'Updating tours…') }}</span></div>
                        <ErrorState v-if="hasNavigationError" :title="t('common.search_error', 'Search could not be updated')" :description="t('common.try_again', 'Please try again in a moment.')"><button type="button" class="public-button public-button--outline" @click="submitSearch">{{ t('common.try_again', 'Try again') }}</button></ErrorState>
                        <template v-else-if="tours.length">
                            <div class="tour-search-grid" :class="{ 'tour-search-grid--sparse': tours.length < 3 }"><TourSearchCard v-for="tour in tours" :key="tour.id" :tour="tour" :search="search" /></div>
                            <nav v-if="lastPage > 1" class="tour-search-pagination" :aria-label="t('common.pagination', 'Pagination')"><Link v-if="currentPage > 1" :href="pageUrl(currentPage - 1)" class="tour-search-pagination__arrow" :aria-label="t('common.previous', 'Previous')"><i class="bi bi-arrow-left" data-dir-icon="arrow" aria-hidden="true"></i></Link><div class="tour-search-pagination__pages"><Link v-for="page in lastPage" :key="page" :href="pageUrl(page)" class="tour-search-pagination__page" :class="{ 'is-current': page === currentPage }" :aria-current="page === currentPage ? 'page' : undefined">{{ page }}</Link></div><Link v-if="currentPage < lastPage" :href="pageUrl(currentPage + 1)" class="tour-search-pagination__arrow" :aria-label="t('common.next', 'Next')"><i class="bi bi-arrow-right" data-dir-icon="arrow" aria-hidden="true"></i></Link></nav>
                        </template>
                        <EmptyState v-else :title="t('common.no_tours_found', 'No tours found')" :description="t('common.no_tours_found_description', 'Try changing the destination or clearing a filter to discover another experience.')"><button type="button" class="public-button public-button--outline" @click="clearFilters">{{ t('common.clear_filters', 'Clear filters') }}</button></EmptyState>
                    </section>
                </div>
            </div>
        </main>
    </PublicLayout>
</template>
