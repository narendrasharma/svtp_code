<script setup>
defineProps({
    settings: { type: Object, default: () => ({}) },
});

const services = [
    { icon: 'bi-car-front-fill', title: 'Private rides', text: 'Comfortable vehicle options for transfers and planned journeys.' },
    { icon: 'bi-building-fill', title: 'Hotel Assistance', text: 'Budget to 4-star stays near your darshan route, booked on your behalf.' },
    { icon: 'bi-person-badge-fill', title: 'Local & English-Speaking Guides', text: 'Guides who know temple timings, crowd flow and the stories behind each site.' },
];
</script>

<template>
    <section class="container py-5">
        <p class="section-eyebrow text-center">सेवाएं</p>
        <h2 class="section-title text-center mb-5">{{ settings.heading || 'Everything you need for the journey' }}</h2>
        <div class="row g-4">
            <div v-for="s in services" :key="s.title" class="col-md-4">
                <div class="glass-card p-4 h-100 text-center">
                    <i class="bi" :class="s.icon" style="font-size:1.8rem;color:var(--gulal-deep);"></i>
                    <h6 class="mt-3 text-svtp">{{ s.title }}</h6>
                    <p class="small text-muted mb-0">{{ s.text }}</p>
                </div>
            </div>
        </div>
    </section>
</template>
