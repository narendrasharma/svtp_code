<script setup>
import TourPlanEnquiryForm from './TourPlanEnquiryForm.vue';

defineProps({
    open: { type: Boolean, default: false },
    securityQuestion: { type: String, required: true },
    tourPackage: { type: Object, default: null },
});

const emit = defineEmits(['close']);
</script>

<template>
    <div v-if="open" class="enquiry-modal-backdrop" role="presentation" @click.self="emit('close')">
        <section class="enquiry-modal enquiry-modal-wide glass-card" role="dialog" aria-modal="true" aria-labelledby="tour-enquiry-title">
            <button type="button" class="btn-close enquiry-modal-close" aria-label="Close" @click="emit('close')"></button>
            <h3 id="tour-enquiry-title" class="brand-heading mb-1">Plan Your Braj Tour</h3>
            <p v-if="tourPackage" class="text-muted mb-3">Enquiry for {{ tourPackage.title }}</p>
            <p v-else class="text-muted mb-3">Tell us what you need and our local team will prepare a suitable plan.</p>
            <TourPlanEnquiryForm
                :security-question="securityQuestion"
                :tour-package-id="tourPackage?.id"
                id-prefix="modal-tour-enquiry"
            />
        </section>
    </div>
</template>
