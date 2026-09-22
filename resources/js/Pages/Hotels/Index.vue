<script setup>
import { reactive } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import AppLayout from '../../Layouts/AppLayout.vue';
import Pagination from '../../Components/Pagination.vue';
import SeoHead from '../../Components/SeoHead.vue';
import { appUrl } from '../../appUrl';

const props = defineProps({ properties: Object, filters: Object, types: Array, cities: Array });
const filters = reactive({ search: '', city_id: '', property_type_id: '', star_rating: '', ...props.filters });

function apply() {
    const params = {};
    for (const [k, v] of Object.entries(filters)) if (v !== '' && v !== null) params[k] = v;
    router.get(appUrl('/hotels'), params, { preserveState: true });
}

function stars(n) {
    if (n === null || n === undefined) return '';
    return '★'.repeat(Number(n)) + '☆'.repeat(5 - Number(n));
}
</script>
<template><AppLayout>
<SeoHead title="Hotels & Stays" description="Browse hotels, resorts, homestays and more." />
<div class="container py-4">
<h1 class="mb-1">Hotels &amp; Stays</h1>
<p class="text-muted mb-3">Handpicked places to stay, listed by verified hosts.</p>
<form class="card p-3 mb-3" @submit.prevent="apply"><div class="row g-2">
<div class="col-md-4"><input v-model="filters.search" maxlength="80" placeholder="Search name or address" class="form-control" /></div>
<div class="col-md-2"><select v-model="filters.city_id" class="form-select"><option value="">All cities</option><option v-for="c in cities" :key="c.id" :value="c.id">{{ c.name }}</option></select></div>
<div class="col-md-3"><select v-model="filters.property_type_id" class="form-select"><option value="">All types</option><option v-for="t in types" :key="t.id" :value="t.id">{{ t.name }}</option></select></div>
<div class="col-md-2"><select v-model="filters.star_rating" class="form-select"><option value="">Any stars</option><option v-for="n in [5,4,3,2,1]" :key="n" :value="n">{{ n }}★</option></select></div>
<div class="col-md-1"><button class="btn btn-svtp w-100">Go</button></div>
</div></form>
<div class="row g-3">
<div v-for="p in properties.data" :key="p.slug" class="col-md-6 col-lg-4">
<div class="card h-100">
<img v-if="p.image" :src="p.image" :alt="p.name" class="card-img-top" style="height: 190px; object-fit: cover;" />
<div class="card-body">
<h5 class="card-title mb-1">{{ p.name }}</h5>
<p class="small text-muted mb-1">{{ p.type }}<span v-if="p.star_rating"> · <span class="text-warning">{{ stars(p.star_rating) }}</span></span> · {{ p.city ?? p.country_code }}</p>
<p class="card-text small">{{ p.short_description }}</p>
<p v-if="p.reviews_count" class="small"><strong>{{ Number(p.rating_average).toFixed(1) }} <span class="text-warning">★</span></strong> · {{ p.reviews_count }} {{ p.reviews_count === 1 ? 'review' : 'reviews' }}</p>
<Link :href="appUrl(`/hotels/${p.slug}`)" class="btn btn-sm btn-outline-primary">View Property</Link>
</div></div></div>
</div>
<p v-if="!properties.data.length" class="alert alert-info mt-3">No stays match these filters yet — try widening your search.</p>
<div class="mt-3"><Pagination :links="properties.links" /></div>
</div>
</AppLayout></template>
