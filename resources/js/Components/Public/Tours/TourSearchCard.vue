<script setup>
import { Link } from '@inertiajs/vue3';
import { appUrl } from '../../../appUrl';
import { useLocalization } from '../../../i18n';
import { mediaUrl } from '../homepage';
import ImageWithFallback from '../Media/ImageWithFallback.vue';
import MoneyDisplay from '../UI/MoneyDisplay.vue';
import RatingDisplay from '../UI/RatingDisplay.vue';

const props = defineProps({
    tour: { type: Object, required: true },
    search: { type: Object, required: true },
});

const { t } = useLocalization();

function durationLabel() {
    const days = Number(props.tour.duration_days);
    const nights = Number(props.tour.duration_nights);

    if (!Number.isFinite(days) || days < 1) return null;
    if (days === 1) return t('common.same_day', 'Same day');

    const dayText = `${days} ${days === 1 ? t('common.day', 'day') : t('common.days', 'days')}`;
    if (!Number.isFinite(nights) || nights < 1) return dayText;

    return `${dayText} · ${nights} ${nights === 1 ? t('common.night', 'night') : t('common.nights', 'nights')}`;
}

function tourHref() {
    const params = new URLSearchParams();

    ['travel_date', 'adults', 'children'].forEach((field) => {
        if (props.search[field] !== null && props.search[field] !== undefined && props.search[field] !== '') {
            params.set(field, props.search[field]);
        }
    });

    const query = params.toString();
    return appUrl(props.tour.url + (query ? `?${query}` : ''));
}
</script>

<template>
    <article class="tour-search-card">
        <Link :href="tourHref()" class="tour-search-card__media-link" :aria-label="t('common.view_tour', 'View tour') + ': ' + tour.title">
            <ImageWithFallback
                :src="mediaUrl(tour.image)"
                :alt="tour.title"
                aspect="editorial"
                kind="tour"
                :label="tour.category || t('common.tour', 'Tour')"
            >
                <template #overlay>
                    <span v-if="tour.badges?.includes('featured')" class="tour-search-card__media-badge">
                        <i class="bi bi-stars" aria-hidden="true"></i>{{ t('common.featured', 'Featured') }}
                    </span>
                </template>
            </ImageWithFallback>
        </Link>

        <div class="tour-search-card__body">
            <div class="tour-search-card__identity">
                <div class="tour-search-card__eyebrow">
                    <span v-if="tour.destination" class="tour-search-card__destination">
                        <i class="bi bi-geo-alt" aria-hidden="true"></i>{{ tour.destination }}
                    </span>
                    <span v-if="tour.category" class="public-badge public-badge--neutral">{{ tour.category }}</span>
                </div>
                <h2 class="tour-search-card__title"><Link :href="tourHref()">{{ tour.title }}</Link></h2>
                <p class="tour-search-card__meta">
                    <span v-if="durationLabel()"><i class="bi bi-clock" aria-hidden="true"></i>{{ durationLabel() }}</span>
                    <span v-if="search.travel_date"><i class="bi bi-calendar3" aria-hidden="true"></i>{{ t('common.travel_date_saved', 'Travel date saved') }}</span>
                </p>
            </div>

            <div v-if="Number(tour.reviews_count) > 0 && tour.rating_average" class="tour-search-card__rating">
                <RatingDisplay :rating="tour.rating_average" :review-count="tour.reviews_count" />
            </div>

            <div class="tour-search-card__footer">
                <div class="tour-search-card__price">
                    <span>{{ t('common.from', 'From') }}</span>
                    <MoneyDisplay :money="tour.display_money" />
                    <small>{{ t('common.starting_price', 'starting price') }}</small>
                </div>
                <Link :href="tourHref()" class="public-button public-button--primary public-button--sm">
                    {{ t('common.view_tour', 'View tour') }}
                    <i class="bi bi-arrow-up-right" data-dir-icon="arrow" aria-hidden="true"></i>
                </Link>
            </div>
        </div>
    </article>
</template>
