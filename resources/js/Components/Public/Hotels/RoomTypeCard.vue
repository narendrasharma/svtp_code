<script setup>
import { computed, ref } from 'vue';
import { useLocalization } from '../../../i18n';
import { mediaUrl } from '../homepage';
import ImageWithFallback from '../Media/ImageWithFallback.vue';
import MoneyDisplay from '../UI/MoneyDisplay.vue';

const props = defineProps({
    room: { type: Object, required: true },
    rates: { type: Array, default: () => [] },
    availability: { type: Object, default: null },
    hasDates: { type: Boolean, default: false },
    selectedRate: { type: Object, default: null },
});

const emit = defineEmits(['select']);
const { t } = useLocalization();
const isExpanded = ref(false);
const primaryImage = computed(() => props.room.image || props.room.gallery?.[0]?.url || null);

function mealLabel(value) {
    return String(value || '').replaceAll('_', ' ').replace(/\b\w/g, (character) => character.toUpperCase());
}

function cancellationLabel(value) {
    if (value === 'flexible') return t('common.flexible_cancellation', 'Flexible cancellation');
    if (value === 'non_refundable') return t('common.non_refundable', 'Non-refundable');
    return null;
}

function isSelected(rate) {
    return props.selectedRate?.code === rate.code;
}
</script>

<template>
    <article class="property-room-card">
        <div class="property-room-card__media">
            <ImageWithFallback :src="mediaUrl(primaryImage)" :alt="room.name" aspect="editorial" kind="hotel" :label="room.name" loading="lazy" />
        </div>
        <div class="property-room-card__content">
            <div class="property-room-card__identity">
                <span class="public-eyebrow">{{ t('common.room_type', 'Room type') }}</span>
                <h3>{{ room.name }}</h3>
                <p v-if="room.short_description">{{ room.short_description }}</p>
                <div class="property-room-card__facts">
                    <span v-if="room.max_occupancy"><i class="bi bi-people" aria-hidden="true"></i>{{ t('common.sleeps', 'Sleeps') }} {{ room.max_occupancy }}</span>
                    <span v-if="room.beds"><i class="bi bi-moon-stars" aria-hidden="true"></i>{{ room.beds }}</span>
                    <span v-if="room.size"><i class="bi bi-arrows-angle-expand" aria-hidden="true"></i>{{ room.size }}</span>
                </div>
                <div v-if="room.amenities?.length" class="property-room-card__amenities">
                    <span v-for="amenity in room.amenities.slice(0, 4)" :key="amenity.name">{{ amenity.name }}</span>
                    <span v-if="room.amenities.length > 4">+{{ room.amenities.length - 4 }}</span>
                </div>
                <button v-if="room.description || room.customFields?.length || room.gallery?.length > 1" type="button" class="public-button public-button--text property-room-card__details-toggle" @click="isExpanded = !isExpanded">
                    {{ isExpanded ? t('common.hide_details', 'Hide details') : t('common.view_room_details', 'View room details') }}
                </button>
                <div v-if="isExpanded" class="property-room-card__expanded">
                    <div v-if="room.description" v-html="room.description"></div>
                    <div v-for="group in room.customFields ?? []" :key="group.group">
                        <strong>{{ group.group }}</strong>
                        <p v-for="field in group.fields" :key="field.label || field.value">{{ field.label ? field.label + ': ' : '' }}{{ field.value }}</p>
                    </div>
                </div>
            </div>

            <div class="property-room-card__rates">
                <div v-if="!hasDates" class="property-room-card__no-date-state">
                    <i class="bi bi-calendar2-week" aria-hidden="true"></i>
                    <span>{{ t('common.select_dates_for_rates', 'Select dates to see availability and rates.') }}</span>
                </div>
                <template v-else-if="rates.length">
                    <div v-for="rate in rates" :key="rate.code" class="property-rate-option" :class="{ 'is-selected': isSelected(rate), 'is-unavailable': !rate.available }">
                        <div class="property-rate-option__details">
                            <strong>{{ rate.name }}</strong>
                            <span v-if="rate.meal_plan">{{ mealLabel(rate.meal_plan) }}</span>
                            <span v-if="cancellationLabel(rate.cancellation_mode)">{{ cancellationLabel(rate.cancellation_mode) }}</span>
                            <small v-if="rate.taxes?.length || rate.fees?.length">{{ t('common.taxes_fees_included', 'Taxes and fees included where applicable') }}</small>
                            <small v-if="!rate.available" class="property-rate-option__unavailable">{{ rate.unavailable_reason || t('common.unavailable_for_dates', 'Unavailable for these dates') }}</small>
                        </div>
                        <div class="property-rate-option__action">
                            <MoneyDisplay v-if="rate.display_total" :money="rate.display_total" />
                            <small v-if="rate.nights_count">{{ rate.nights_count }} {{ rate.nights_count === 1 ? t('common.night', 'night') : t('common.nights', 'nights') }}</small>
                            <button type="button" class="public-button public-button--primary public-button--sm" :disabled="!rate.available" @click="emit('select', rate)">
                                {{ isSelected(rate) ? t('common.selected', 'Selected') : t('common.select_rate', 'Select rate') }}
                            </button>
                        </div>
                    </div>
                </template>
                <div v-else class="property-room-card__no-date-state">
                    <i class="bi bi-info-circle" aria-hidden="true"></i>
                    <span>{{ t('common.no_rates_for_stay', 'No rate is available for this stay.') }}</span>
                </div>
            </div>
        </div>
    </article>
</template>
