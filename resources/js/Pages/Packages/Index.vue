<script setup>
import { appUrl } from '../../appUrl';
import AppLayout from '../../Layouts/AppLayout.vue';
import PackageCard from '../../Components/PackageCard.vue';
import { router } from '@inertiajs/vue3';
import { reactive, ref } from 'vue';
import { whyChooseUs, faqItems, attractions } from '../../festiveAssets';

const openFaq = ref(0);
function toggleFaq(i) { openFaq.value = openFaq.value === i ? -1 : i; }

const props = defineProps({ packages: Object, filters: Object });

const filters = reactive({ ...props.filters });

const categories = [
    { value: '', label: 'All Tours', icon: 'bi-grid-fill' },
    { value: 'braj', label: 'Vrindavan & Mathura', icon: 'bi-flower3' },
    { value: 'temple', label: 'Temple Trails', icon: 'bi-bank2' },
    { value: 'up_circuit', label: 'UP Circuit', icon: 'bi-signpost-2-fill' },
    { value: 'rajasthan', label: 'Rajasthan', icon: 'bi-building' },
    { value: 'uttarakhand', label: 'Uttarakhand', icon: 'bi-water' },
];

function applyFilters() {
    router.get(appUrl('/packages'), filters, { preserveState: true, preserveScroll: true });
}

function setCategory(value) {
    filters.category = value;
    applyFilters();
}

function resetFilters() {
    filters.category = '';
    filters.min_price = '';
    filters.max_price = '';
    filters.city = '';
    applyFilters();
}
</script>

<template>
    <AppLayout>
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
                    :key="c.value"
                    type="button"
                    class="category-chip border-0"
                    :class="{ 'is-active': (filters.category || '') === c.value }"
                    @click="setCategory(c.value)"
                >
                    <i class="bi" :class="c.icon"></i>
                    <strong>{{ c.label }}</strong>
                </button>
            </div>

            <!-- Price filters -->
            <div class="glass-card p-3 p-md-4 mb-5">
                <div class="row g-3 align-items-end">
                    <div class="col-6 col-md-3">
                        <label class="form-label small fw-semibold text-svtp">Min Price (₹)</label>
                        <input v-model="filters.min_price" type="number" placeholder="e.g. 3000" class="form-control" @change="applyFilters" />
                    </div>
                    <div class="col-6 col-md-3">
                        <label class="form-label small fw-semibold text-svtp">Max Price (₹)</label>
                        <input v-model="filters.max_price" type="number" placeholder="e.g. 15000" class="form-control" @change="applyFilters" />
                    </div>
                    <div class="col-6 col-md-3">
                        <label class="form-label small fw-semibold text-svtp">Travel Date</label>
                        <input v-model="filters.date" type="date" class="form-control" />
                    </div>
                    <div class="col-6 col-md-3 d-flex gap-2">
                        <button class="btn btn-svtp flex-grow-1" @click="applyFilters">Apply</button>
                        <button class="btn btn-outline-svtp" @click="resetFilters">Reset</button>
                    </div>
                </div>
            </div>

            <p class="text-muted mb-4">{{ packages.total ?? packages.data.length }} tours found</p>
            <div v-if="filters.pickup_address || filters.date || filters.adults" class="alert alert-light border mb-4">
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
