<script setup>
import AppLayout from '../../Layouts/AppLayout.vue';
import { appUrl } from '../../appUrl';
import { attractions, categoryImages } from '../../festiveAssets';

const destinations = [
    { name: 'Vrindavan', image: categoryImages.braj, note: 'Banke Bihari, Prem Mandir, ISKCON, Nidhivan, Seva Kunj, Keshi Ghat' },
    { name: 'Mathura', image: categoryImages.temple, note: 'Krishna Janmabhoomi, Dwarkadhish Temple, Vishram Ghat, Birla Mandir' },
    { name: 'Govardhan', image: categoryImages.braj, note: 'Govardhan Parikrama, Radha Kund, Kusum Sarovar' },
    { name: 'Barsana & Nandgaon', image: categoryImages.festival, note: 'Radha Rani Temple, home of Lathmar Holi' },
    { name: 'Gokul & Mahavan', image: categoryImages.braj, note: 'Krishna\'s childhood villages across the Yamuna' },
    { name: 'Baldeo', image: categoryImages.temple, note: 'Dauji Temple, dedicated to Balaram' },
];
</script>

<template>
    <AppLayout>
        <section class="trust-strip py-5 mb-4">
            <div class="container text-center">
                <p class="section-eyebrow text-white opacity-75">ब्रज दर्शन</p>
                <h1 class="text-white" style="font-family: var(--font-display);">Explore Braj</h1>
                <p class="opacity-75 mb-0">The villages, temples and ghats every darshan route is built around.</p>
            </div>
        </section>

        <div class="container py-2">
            <div class="row g-4 mb-5">
                <div v-for="d in destinations" :key="d.name" class="col-md-4">
                    <div class="attraction-card">
                        <img :src="d.image" :alt="d.name" loading="lazy" style="height: 220px;" />
                        <div class="attraction-card-body">
                            <h5 class="text-svtp mb-1">{{ d.name }}</h5>
                            <p class="small text-muted mb-3">{{ d.note }}</p>
                            <a :href="appUrl('/packages')" class="btn btn-outline-svtp btn-sm">View Tours</a>
                        </div>
                    </div>
                </div>
            </div>

            <h2 class="section-title text-center mb-5">Temples &amp; Landmarks</h2>
            <div class="row g-4">
                <div v-for="a in attractions" :key="a.name" class="col-md-3 col-6">
                    <div class="attraction-card">
                        <img :src="a.image" :alt="a.name" loading="lazy" />
                        <div class="attraction-card-body">
                            <h6 class="text-svtp mb-1">{{ a.name }}</h6>
                            <p class="small text-muted mb-0">{{ a.blurb }}</p>
                        </div>
                    </div>
                </div>
            </div>

            <p class="text-center text-muted small mt-5 mb-5">
                Individual destination pages with timings, maps and travel tips for each of these places are
                planned next — see the roadmap note at the end of this delivery.
            </p>
        </div>
    </AppLayout>
</template>
