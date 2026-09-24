<script setup>
import { reactive } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import AppLayout from '../../Layouts/AppLayout.vue';
import Pagination from '../../Components/Pagination.vue';
import SeoHead from '../../Components/SeoHead.vue';
import LocationField from '../../Components/Public/Search/LocationField.vue';
import ImageWithFallback from '../../Components/Public/Media/ImageWithFallback.vue';
import { mediaUrl } from '../../Components/Public/homepage';
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
<template>
    <AppLayout>
        <SeoHead title="Hotels & Stays" description="Browse hotels, resorts, homestays and more." />
        <div class="public-container hotel-listing">
            <h1>Hotels &amp; Stays</h1>
            <p class="hotel-listing__intro">Handpicked places to stay, listed by verified hosts.</p>

            <form class="hotel-listing__filters" @submit.prevent="apply">
                <LocationField id="hotel-listing-location" v-model="filters.search" label="Location" placeholder="Search name or address" />
                <label class="public-field" for="hotel-listing-city">
                    <span class="public-field__label">City</span>
                    <select id="hotel-listing-city" v-model="filters.city_id" class="public-select-input">
                        <option value="">All cities</option>
                        <option v-for="city in cities" :key="city.id" :value="city.id">{{ city.name }}</option>
                    </select>
                </label>
                <label class="public-field" for="hotel-listing-type">
                    <span class="public-field__label">Property type</span>
                    <select id="hotel-listing-type" v-model="filters.property_type_id" class="public-select-input">
                        <option value="">All types</option>
                        <option v-for="type in types" :key="type.id" :value="type.id">{{ type.name }}</option>
                    </select>
                </label>
                <label class="public-field" for="hotel-listing-stars">
                    <span class="public-field__label">Stars</span>
                    <select id="hotel-listing-stars" v-model="filters.star_rating" class="public-select-input">
                        <option value="">Any stars</option>
                        <option v-for="rating in [5, 4, 3, 2, 1]" :key="rating" :value="rating">{{ rating }}★</option>
                    </select>
                </label>
                <button type="submit" class="public-button public-button--primary hotel-listing__search">Search</button>
            </form>

            <div class="hotel-listing__grid">
                <article v-for="property in properties.data" :key="property.slug" class="hotel-listing-card">
                    <Link :href="appUrl(`/hotels/${property.slug}`)" class="hotel-listing-card__media" :aria-label="`View ${property.name}`">
                        <ImageWithFallback :src="mediaUrl(property.image)" :alt="property.name" aspect="editorial" kind="hotel" :label="property.type || 'Stay'" />
                    </Link>
                    <div class="hotel-listing-card__body">
                        <div class="hotel-listing-card__meta">
                            <span v-if="property.type">{{ property.type }}</span>
                            <span v-if="property.star_rating" class="hotel-listing-card__stars" :aria-label="`${property.star_rating} property stars`">{{ stars(property.star_rating) }}</span>
                            <span v-if="property.city || property.country_code" class="hotel-listing-card__location"><i class="bi bi-geo-alt" aria-hidden="true"></i>{{ property.city || property.country_code }}</span>
                        </div>
                        <h2 class="hotel-listing-card__title">{{ property.name }}</h2>
                        <p v-if="property.short_description" class="hotel-listing-card__description">{{ property.short_description }}</p>
                        <p v-if="property.reviews_count" class="hotel-listing-card__rating"><strong>{{ Number(property.rating_average).toFixed(1) }} <span aria-hidden="true">★</span></strong> · {{ property.reviews_count }} {{ property.reviews_count === 1 ? 'review' : 'reviews' }}</p>
                        <Link :href="appUrl(`/hotels/${property.slug}`)" class="public-button public-button--primary hotel-listing-card__action">View property <i class="bi bi-arrow-up-right" aria-hidden="true"></i></Link>
                    </div>
                </article>
            </div>
            <p v-if="!properties.data.length" class="alert alert-info mt-3">No stays match these filters yet — try widening your search.</p>
            <div class="hotel-listing__pagination"><Pagination :links="properties.links" /></div>
        </div>
    </AppLayout>
</template>
