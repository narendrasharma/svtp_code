<script setup>
import { contactInfo } from '../../festiveAssets';

defineProps({
    settings: { type: Object, default: () => ({}) },
});
</script>

<template>
    <section class="container py-5">
        <p class="section-eyebrow text-center">संपर्क करें</p>
        <h2 class="section-title text-center mb-5">{{ settings.heading || 'Contact the travel team' }}</h2>
        <div class="row g-4">
            <div class="col-lg-5">
                <div class="glass-card p-4 h-100">
                    <h5 class="text-svtp">{{ contactInfo.companyName }}</h5>
                    <p class="mb-3"><strong>CEO / Travel Consultant:</strong><br>{{ contactInfo.ceo }}<br><a :href="contactInfo.phoneHref">{{ contactInfo.phone }}</a></p>
                    <p class="mb-3"><strong>Marketing Head:</strong><br>{{ contactInfo.marketingHead }}<br><a :href="contactInfo.marketingPhoneHref">{{ contactInfo.marketingPhone }}</a></p>
                    <p class="mb-0"><i class="bi bi-envelope-fill me-2 text-svtp"></i><a :href="`mailto:${contactInfo.email}`">{{ contactInfo.email }}</a></p>
                </div>
            </div>
            <div class="col-lg-7">
                <div class="glass-card p-4 h-100">
                    <h5 class="text-svtp"><i class="bi bi-geo-alt-fill me-2"></i>Head Office</h5>
                    <p>{{ contactInfo.headOffice }}</p>
                    <a :href="contactInfo.mapUrl" target="_blank" rel="noopener" class="btn btn-outline-svtp">View on Google Maps</a>
                </div>
            </div>
        </div>
    </section>
</template>
