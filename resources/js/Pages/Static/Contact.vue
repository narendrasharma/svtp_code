<script setup>
import AppLayout from '../../Layouts/AppLayout.vue';
import { contactInfo } from '../../festiveAssets';
</script>

<template>
    <AppLayout>
        <div class="container py-5">
            <p class="section-eyebrow">संपर्क करें</p>
            <h1 class="brand-heading">Contact {{ contactInfo.companyName }}</h1>
            <p class="text-muted mb-5">Speak directly with our Mathura-based team or visit our head office.</p>
            <div class="row g-4">
                <div class="col-lg-5">
                    <div class="glass-card p-4 h-100">
                        <h4 class="text-svtp mb-4">Contact Details</h4>
                        <p><strong>CEO / Travel Consultant</strong><br>{{ contactInfo.ceo }}<br><a :href="contactInfo.phoneHref">{{ contactInfo.phone }}</a>, <a :href="contactInfo.phone2Href">{{ contactInfo.phone2 }}</a></p>
                            <p><strong>Head – Tech &amp; Digital Operations</strong><br>{{ contactInfo.marketingHead }}<br><a :href="contactInfo.marketingPhoneHref">{{ contactInfo.marketingPhone }}</a></p>
                        <p>
                            <strong>Travel Consultants</strong><br>
                            <template v-for="consultant in contactInfo.travelConsultants" :key="consultant.name">
                                {{ consultant.name }}<template v-if="consultant.phone"> · <a :href="consultant.phoneHref">{{ consultant.phone }}</a></template><br>
                            </template>
                        </p>
                        <p><strong>Email</strong><br><a :href="`mailto:${contactInfo.email}`">{{ contactInfo.email }}</a></p>
                        <p><strong>Website</strong><br><a :href="contactInfo.websiteHref">{{ contactInfo.website }}</a></p>
                        <hr>
                        <p><strong>Head Office</strong><br>{{ contactInfo.headOffice }}</p>
                        <a :href="contactInfo.mapUrl" target="_blank" rel="noopener" class="btn btn-svtp"><i class="bi bi-geo-alt-fill me-2"></i>Open in Google Maps</a>
                    </div>
                </div>
                <div class="col-lg-7">
                    <div class="glass-card overflow-hidden h-100 contact-map-card">
                        <iframe :src="contactInfo.mapEmbedUrl" title="Shree Vrindavan Tour Packages at Pawan Kunj map" loading="lazy" referrerpolicy="strict-origin-when-cross-origin" allowfullscreen></iframe>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
