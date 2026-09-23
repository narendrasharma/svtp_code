<script setup>
import { Link } from '@inertiajs/vue3';
import { appUrl } from '../../../appUrl';
import { useLocalization } from '../../../i18n';
import { mediaUrl } from '../homepage';
import ImageWithFallback from '../Media/ImageWithFallback.vue';
import MoneyDisplay from '../UI/MoneyDisplay.vue';
import RatingDisplay from '../UI/RatingDisplay.vue';

const props = defineProps({
    hotel: { type: Object, required: true },
    search: { type: Object, required: true },
});

const { t } = useLocalization();

function propertyHref() {
    const params = new URLSearchParams();
    ['check_in', 'check_out', 'rooms', 'adults', 'children'].forEach((field) => {
        if (props.search[field] !== null && props.search[field] !== undefined && props.search[field] !== '') {
            params.set(field, props.search[field]);
        }
    });
    const query = params.toString();

    return appUrl(props.hotel.url + (query ? '?' + query : ''));
}
</script>

<template>
    <article class="hotel-search-card">
        <Link :href="propertyHref()" class="hotel-search-card__media-link" :aria-label="t('common.view_property', 'View property') + ': ' + hotel.name">
            <ImageWithFallback
                :src="mediaUrl(hotel.image)"
                :alt="hotel.name"
                aspect="editorial"
                kind="hotel"
                :label="hotel.property_type || t('common.hotel', 'Hotel')"
            />
        </Link>

        <div class="hotel-search-card__body">
            <div class="hotel-search-card__identity">
                <div class="hotel-search-card__eyebrow">
                    <span v-if="hotel.property_type" class="public-badge public-badge--neutral">{{ hotel.property_type }}</span>
                    <span v-if="hotel.badges?.includes('featured')" class="public-badge public-badge--accent">{{ t('common.featured', 'Featured') }}</span>
                </div>
                <h2 class="hotel-search-card__title"><Link :href="propertyHref()">{{ hotel.name }}</Link></h2>
                <p v-if="hotel.city || hotel.destination" class="hotel-search-card__location">
                    <i class="bi bi-geo-alt" aria-hidden="true"></i>
                    {{ hotel.city || hotel.destination }}
                    <span v-if="hotel.city && hotel.destination && hotel.destination !== hotel.city"> · {{ hotel.destination }}</span>
                </p>
            </div>

            <div class="hotel-search-card__details">
                <RatingDisplay :rating="hotel.rating_average" :review-count="hotel.reviews_count" :label="t('common.not_rated', 'Not rated')" />
                <span v-if="hotel.star_rating" class="hotel-search-card__classification">
                    <i v-for="star in hotel.star_rating" :key="star" class="bi bi-star-fill" aria-hidden="true"></i>
                    <span class="visually-hidden">{{ hotel.star_rating }} {{ t('common.property_stars', 'property stars') }}</span>
                </span>
            </div>

            <div class="hotel-search-card__footer">
                <div v-if="hotel.display_money" class="hotel-search-card__price">
                    <span>{{ t('common.from', 'From') }}</span>
                    <MoneyDisplay :money="hotel.display_money" />
                    <small>{{ t('common.starting_price', 'starting price') }}</small>
                </div>
                <div v-else class="hotel-search-card__price hotel-search-card__price--muted">
                    <span>{{ t('common.price_on_request', 'Price available on request') }}</span>
                </div>
                <Link :href="propertyHref()" class="public-button public-button--primary public-button--sm">
                    {{ t('common.view_property', 'View property') }}
                    <i class="bi bi-arrow-up-right" data-dir-icon="arrow" aria-hidden="true"></i>
                </Link>
            </div>
        </div>
    </article>
</template>
