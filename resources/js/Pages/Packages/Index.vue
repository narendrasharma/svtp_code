    <script setup>
import { appUrl } from '../../appUrl';
import AppLayout from '../../Layouts/AppLayout.vue';
import PackageCard from '../../Components/PackageCard.vue';
import { router } from '@inertiajs/vue3';
import { computed, reactive, ref } from 'vue';
import { whyChooseUs, faqItems, attractions } from '../../festiveAssets';
import SeoHead from "@/Components/SeoHead.vue";

const openFaq = ref(0);
function toggleFaq(i) { openFaq.value = openFaq.value === i ? -1 : i; }

const props = defineProps({
    packages: Object,
    filters: Object,
    places: { type: Array, default: () => [] },
    tags: { type: Array, default: () => [] },
    categories: { type: Array, default: () => [] },
});

const filters = reactive({ ...props.filters });
filters.tags = [...(filters.tags || [])];
const selectedPlace = props.places.find((place) => place.slug === filters.place);
const placeQuery = ref(selectedPlace?.name || '');
const placeOpen = ref(false);

const filteredPlaces = computed(() => {
    const query = placeQuery.value.trim().toLowerCase();

    if (!query) {
        return props.places;
    }

    return props.places.filter((place) =>
        place.name.toLowerCase().includes(query)
        || place.destination?.name?.toLowerCase().includes(query)
    );
});

const categories = computed(() => [
    { slug: '', name: 'All Tours', icon: 'bi-grid-fill' },
    ...props.categories,
]);

function applyFilters() {
    router.get(appUrl('/packages'), filters, { preserveState: true, preserveScroll: true });
}

function setCategory(value) {
    filters.category = value;
    applyFilters();
}

function updatePlaceQuery() {
    filters.place = '';
    placeOpen.value = true;
}

function selectPlace(place) {
    filters.place = place.slug;
    placeQuery.value = place.name;
    placeOpen.value = false;
}

function toggleTag(slug) {
    filters.tags = filters.tags.includes(slug)
        ? filters.tags.filter((tag) => tag !== slug)
        : [...filters.tags, slug];
    applyFilters();
}

function clearTags() {
    filters.tags = [];
    applyFilters();
}

function resetFilters() {
    filters.category = '';
    filters.min_price = '';
    filters.max_price = '';
    filters.destination = '';
    filters.place = '';
    filters.tags = [];
    placeQuery.value = '';
    filters.city = '';
    applyFilters();
}
</script>

<template>
    <AppLayout>
        <SeoHead
            title="Tour Packages"
            description="Browse our available tour packages, itineraries, destinations and travel experiences."
        />
        <section class="trust-strip py-5 mb-4">
            <div class="container text-center">
                <p class="section-eyebrow text-white opacity-75">दर्शन यात्रा निर्देशिका</p>
                <h1 class="text-white" style="font-family: var(--font-display);">Destinations &amp; Tour Packages</h1>
                <p class="opacity-75 mb-0">Vrindavan, Mathura, Agra, Delhi same-day trips, temple trails and Holi specials — all in one place.</p>
            </div>
        </section>

        <div class="container py-2">
            <!-- Category chips -->
            <div class="d-flex flex-wrap gap-3 justify-content-center mb-4">
                <button
                    v-for="c in categories"
                    :key="c.slug"
                    type="button"
                    class="category-chip border-0"
                    :class="{ 'is-active': (filters.category || '') === c.slug }"
                    @click="setCategory(c.slug)"
                >
                    <i class="bi" :class="c.icon || 'bi-map'"></i>
                    <strong>{{ c.name }}</strong>
                </button>
            </div>

            <div v-if="tags.length" class="mb-4 text-center">
                <p class="small fw-semibold text-uppercase text-muted mb-2">Choose your travel style</p>
                <div class="d-flex flex-wrap gap-2 justify-content-center" role="group" aria-label="Filter tours by travel style">
                    <button
                        type="button"
                        class="tag-filter-chip"
                        :class="{ 'is-active': !filters.tags.length }"
                        :aria-pressed="!filters.tags.length"
                        @click="clearTags"
                    >
                        All experiences
                    </button>
                    <button
                        v-for="tag in tags"
                        :key="tag.id"
                        type="button"
                        class="tag-filter-chip"
                        :class="{ 'is-active': filters.tags.includes(tag.slug) }"
                        :aria-pressed="filters.tags.includes(tag.slug)"
                        @click="toggleTag(tag.slug)"
                    >
                        {{ tag.name }}
                    </button>
                </div>
            </div>

            <!-- Tour filters -->
            <div class="glass-card p-3 p-md-4 mb-5">
                <div class="row g-3 align-items-end">
                    <div class="col-12 col-md-4 position-relative">
                        <label class="form-label small fw-semibold text-svtp">Place / Attraction</label>
                        <input
                            v-model="placeQuery"
                            type="search"
                            class="form-control"
                            placeholder="Taj Mahal, Prem Mandir..."
                            autocomplete="off"
                            @input="updatePlaceQuery"
                            @focus="placeOpen = true"
                            @keydown.escape="placeOpen = false"
                        />
                        <div v-if="placeOpen" class="search-dropdown shadow-sm">
                            <button
                                v-for="place in filteredPlaces"
                                :key="place.id"
                                type="button"
                                class="dropdown-item text-start"
                                @mousedown.prevent="selectPlace(place)"
                            >
                                <strong>{{ place.name }}</strong>
                                <small v-if="place.destination" class="d-block text-muted">{{ place.destination.name }}</small>
                            </button>
                            <p v-if="!filteredPlaces.length" class="small text-muted px-3 py-2 mb-0">No matching attractions found.</p>
                        </div>
                    </div>
                    <div class="col-6 col-md-2">
                        <label class="form-label small fw-semibold text-svtp">Min Price (₹)</label>
                        <input v-model="filters.min_price" type="number" placeholder="e.g. 3000" class="form-control" @change="applyFilters" />
                    </div>
                    <div class="col-6 col-md-2">
                        <label class="form-label small fw-semibold text-svtp">Max Price (₹)</label>
                        <input v-model="filters.max_price" type="number" placeholder="e.g. 15000" class="form-control" @change="applyFilters" />
                    </div>
                    <div class="col-6 col-md-2">
                        <label class="form-label small fw-semibold text-svtp">Travel Date</label>
                        <input v-model="filters.date" type="date" class="form-control" />
                    </div>
                    <div class="col-12 col-md-2 d-flex gap-2">
                        <button class="btn btn-svtp flex-grow-1" @click="applyFilters">Apply</button>
                        <button class="btn btn-outline-svtp" @click="resetFilters">Reset</button>
                    </div>
                </div>
            </div>

            <p class="text-muted mb-4">{{ packages.total ?? packages.data.length }} tours found</p>
            <div v-if="filters.place || filters.pickup_address || filters.date || filters.adults" class="alert alert-light border mb-4">
                <span v-if="filters.place"><strong>Attraction:</strong> {{ placeQuery }}</span>
                <span v-if="filters.pickup_address"><strong>Pickup:</strong> {{ filters.pickup_address }}</span>
                <span v-if="filters.date" class="ms-3"><strong>Travel date:</strong> {{ filters.date }}</span>
                <span v-if="filters.adults" class="ms-3">
                    <strong>Travellers:</strong> {{ filters.adults }} adult<span v-if="Number(filters.adults) !== 1">s</span>
                    <template v-if="Number(filters.children)">, {{ filters.children }} child<span v-if="Number(filters.children) !== 1">ren</span></template>
                </span>
            </div>

            <div v-if="packages.data.length" class="row g-4">
                <div v-for="pkg in packages.data" :key="pkg.id" class="col-md-4">
                    <PackageCard :pkg="pkg" />
                </div>
            </div>

            <div v-else class="text-center py-5">
                <i class="bi bi-emoji-frown display-4 text-muted"></i>
                <p class="mt-3 text-muted">No tours match those filters just yet. Try widening your price range.</p>
                <button class="btn btn-outline-svtp mt-2" @click="resetFilters">Clear Filters</button>
            </div>

            <!-- Pagination -->
            <nav v-if="packages.links && packages.links.length > 3" class="d-flex justify-content-center mt-5">
                <ul class="pagination">
                    <li v-for="(link, i) in packages.links" :key="i" class="page-item" :class="{ active: link.active, disabled: !link.url }">
                        <button
                            class="page-link"
                            v-html="link.label"
                            :disabled="!link.url"
                            @click="link.url && router.get(link.url, {}, { preserveState: true, preserveScroll: true })"
                        ></button>
                    </li>
                </ul>
            </nav>
        </div>

        <!-- Why book with us -->
        <section class="bg-cream-warm py-5 mt-5">
            <div class="container">
                <h2 class="section-title text-center mb-5">Why Book Through Us?</h2>
                <div class="row g-4">
                    <div v-for="w in whyChooseUs" :key="w.title" class="col-md-3 col-6 text-center">
                        <i class="bi" :class="w.icon" style="font-size:1.6rem;color:var(--gulal-deep);"></i>
                        <h6 class="mt-2 text-svtp">{{ w.title }}</h6>
                        <p class="small text-muted">{{ w.text }}</p>
                    </div>
                </div>
            </div>
        </section>

        <!-- Popular destinations quick links -->
        <section class="container py-5">
            <h2 class="section-title text-center mb-5">Popular Destinations in Braj</h2>
            <div class="row g-4">
                <div v-for="a in attractions" :key="a.name" class="col-md-3 col-6">
                    <div class="attraction-card">
                        <img :src="a.image" :alt="a.name" loading="lazy" />
                        <div class="attraction-card-body">
                            <h6 class="text-svtp mb-0">{{ a.name }}</h6>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- FAQ -->
        <section class="bg-cream-warm py-5">
            <div class="container">
                <h2 class="section-title text-center mb-5">Tour Package FAQs</h2>
                <div class="row justify-content-center">
                    <div class="col-lg-8">
                        <div v-for="(f, i) in faqItems" :key="f.q" class="faq-item" @click="toggleFaq(i)">
                            <div class="faq-q">
                                <span>{{ f.q }}</span>
                                <i class="bi" :class="openFaq === i ? 'bi-dash-circle-fill' : 'bi-plus-circle-fill'" style="color: var(--gulal);"></i>
                            </div>
                            <p v-if="openFaq === i" class="faq-a">{{ f.a }}</p>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </AppLayout>
</template>

<style scoped>
.tag-filter-chip {
    border: 1px solid rgba(37, 74, 135, 0.2);
    border-radius: 999px;
    background: #fff;
    color: var(--yamuna);
    font-size: 0.875rem;
    font-weight: 600;
    padding: 0.55rem 1rem;
    transition: border-color 0.2s ease, box-shadow 0.2s ease, color 0.2s ease, transform 0.2s ease;
}

.tag-filter-chip:hover {
    border-color: var(--gulal);
    color: var(--gulal-deep);
    box-shadow: 0 0.35rem 1rem rgba(207, 65, 112, 0.12);
    transform: translateY(-1px);
}

.tag-filter-chip.is-active {
    border-color: var(--gulal-deep);
    background: var(--gulal-deep);
    color: #fff;
    box-shadow: 0 0.4rem 1rem rgba(166, 42, 91, 0.2);
}

.tag-filter-chip:focus-visible {
    outline: 3px solid rgba(244, 166, 35, 0.35);
    outline-offset: 2px;
}
</style>
