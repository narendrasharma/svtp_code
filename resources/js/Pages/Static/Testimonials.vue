<script setup>
import AppLayout from '../../Layouts/AppLayout.vue';
import EmptyState from '../../Components/Public/States/EmptyState.vue';
import PublicPageHero from '../../Components/Public/Content/PublicPageHero.vue';
import SeoHead from '../../Components/SeoHead.vue';
import StarRating from '../../Components/StarRating.vue';
import { appUrl } from '../../appUrl';

const props = defineProps({
    testimonials: { type: Array, default: () => [] },
});
</script>

<template>
    <AppLayout>
        <SeoHead
            title="Guest reviews"
            description="Read published guest reviews from this travel marketplace."
            :canonical="appUrl('/testimonials')"
            :noindex="!testimonials.length"
        />
        <PublicPageHero
            eyebrow="Published feedback"
            title="Guest reviews"
            description="Read feedback that has been approved for public display."
        />
        <main class="public-container py-5">
            <div v-if="testimonials.length" class="row g-4">
                <div v-for="testimonial in testimonials" :key="testimonial.id" class="col-md-6 col-xl-4">
                    <article class="public-content-panel h-100">
                        <StarRating :rating="testimonial.rating" />
                        <p class="mt-3 mb-4">{{ testimonial.comment }}</p>
                        <p class="fw-semibold mb-1">{{ testimonial.reviewer_name || 'Guest' }}</p>
                        <p v-if="testimonial.package_title" class="small text-muted mb-0">{{ testimonial.package_title }}</p>
                        <time v-if="testimonial.published_at" class="small text-muted" :datetime="testimonial.published_at">{{ testimonial.published_at }}</time>
                    </article>
                </div>
            </div>
            <EmptyState v-else title="Reviews will appear here" description="Published guest feedback will appear here after it has been approved.">
                <template #icon><i class="bi bi-chat-quote" aria-hidden="true"></i></template>
            </EmptyState>
        </main>
    </AppLayout>
</template>

<style scoped>
.public-content-panel {
    padding: 1.5rem;
    border: 1px solid rgba(31, 72, 67, 0.1);
    border-radius: 1.25rem;
    background: #fffdf8;
}
</style>
