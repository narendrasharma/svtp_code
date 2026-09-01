<script setup>
import { ref, computed } from 'vue';
import { appUrl } from '../../appUrl';
import AppLayout from '../../Layouts/AppLayout.vue';
import ItineraryAccordion from '../../Components/ItineraryAccordion.vue';
import StarRating from '../../Components/StarRating.vue';
import { categoryImage } from '../../festiveAssets';

const props = defineProps({ package: Object, reviews: Object });

const gallery = computed(() => {
    const g = props.package.gallery && props.package.gallery.length
        ? props.package.gallery
        : [props.package.cover_image || categoryImage(props.package.category)];
    return g.filter(Boolean);
});

const activeImage = ref(0);

const adults = ref(2);
const children = ref(0);
const unitPrice = computed(() => Number(props.package.discounted_price || props.package.price || 0));
const childPrice = computed(() => Math.round(unitPrice.value * 0.6));
const totalPrice = computed(() => (adults.value * unitPrice.value) + (children.value * childPrice.value));
</script>

<template>
    <AppLayout>
        <div class="container py-4">
            <!-- Gallery -->
            <div class="gallery-hero mb-2">
                <img :src="gallery[activeImage]" :alt="package.title" />
            </div>
            <div v-if="gallery.length > 1" class="gallery-thumbs mb-4">
                <div
                    v-for="(img, i) in gallery"
                    :key="i"
                    class="gallery-thumb"
                    :class="{ 'is-active': i === activeImage }"
                    @click="activeImage = i"
                >
                    <img :src="img" :alt="`${package.title} photo ${i + 1}`" loading="lazy" />
                </div>
            </div>

            <div class="row g-5">
                <div class="col-lg-8">
                    <p class="section-eyebrow mb-1">{{ package.city?.name }}</p>
                    <h1 style="font-family: var(--font-display);">{{ package.title }}</h1>
                    <div class="d-flex align-items-center gap-3 mb-3">
                        <p class="text-muted mb-0"><i class="bi bi-calendar3 me-1"></i>{{ package.duration_days }} Days / {{ package.duration_nights }} Nights</p>
                        <StarRating v-if="package.approved_reviews_avg_rating" :rating="package.approved_reviews_avg_rating" />
                    </div>
                    <p>{{ package.overview }}</p>

                    <!-- Spiritual significance -->
                    <div class="shloka-quote-card my-4">
                        <p class="devanagari fs-5 mb-2">॥ वृन्दावनं परित्यज्य पादमेकं न गच्छति ॥</p>
                        <p class="mb-0 small">
                            This journey traces the pastimes of Shree Krishna through {{ package.city?.name || 'Braj' }} —
                            a route walked by pilgrims for centuries in search of the same darshan you're about to experience.
                        </p>
                    </div>

                    <h4 class="mt-4">Day-wise Itinerary</h4>
                    <ItineraryAccordion :days="package.day_wise_itinerary || []" />

                    <div class="row mt-4">
                        <div class="col-md-6">
                            <h5><i class="bi bi-check-circle-fill text-success me-1"></i>Inclusions</h5>
                            <ul>
                                <li v-for="(item, i) in package.inclusions || []" :key="i">{{ item }}</li>
                            </ul>
                        </div>
                        <div class="col-md-6">
                            <h5><i class="bi bi-x-circle-fill text-danger me-1"></i>Exclusions</h5>
                            <ul>
                                <li v-for="(item, i) in package.exclusions || []" :key="i">{{ item }}</li>
                            </ul>
                        </div>
                    </div>

                    <h4 class="mt-4">Reviews</h4>
                    <div v-if="!reviews.data.length" class="text-muted small">No reviews yet — be the first to share your experience.</div>
                    <div v-for="review in reviews.data" :key="review.id" class="border-bottom py-3">
                        <StarRating :rating="review.rating" />
                        <p class="mb-0 mt-1">{{ review.comment }}</p>
                        <small class="text-muted">— {{ review.user?.name }}</small>
                    </div>
                </div>

                <div class="col-lg-4">
                    <div class="glass-card p-4 booking-sticky">
                        <div class="d-flex align-items-baseline gap-2">
                            <span v-if="package.discounted_price" class="price-tag-strike">₹{{ package.price }}</span>
                            <p class="price-tag mb-0">₹{{ package.discounted_price || package.price }}</p>
                        </div>
                        <small class="text-muted">per person</small>

                        <hr />

                        <p class="fw-semibold small mb-2">Estimate your total</p>
                        <div class="price-calc-row">
                            <label class="mb-0 small">Adults</label>
                            <input v-model.number="adults" type="number" min="1" class="form-control form-control-sm" style="width: 80px;" />
                        </div>
                        <div class="price-calc-row">
                            <label class="mb-0 small">Children (under 12)</label>
                            <input v-model.number="children" type="number" min="0" class="form-control form-control-sm" style="width: 80px;" />
                        </div>
                        <div class="price-calc-row border-0 pt-3">
                            <span class="fw-semibold">Estimated Total</span>
                            <span class="price-tag fs-4 mb-0">₹{{ totalPrice.toLocaleString('en-IN') }}</span>
                        </div>

                        <a :href="`${appUrl('/packages')}/${package.slug}/checkout`" class="btn btn-svtp w-100 mt-3">
                            <i class="bi bi-calendar-check me-1"></i>Book Now
                        </a>
                        <a href="https://wa.me/917017621518" target="_blank" rel="noopener" class="btn btn-outline-svtp w-100 mt-2">
                            <i class="bi bi-whatsapp me-1"></i>Ask on WhatsApp
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
