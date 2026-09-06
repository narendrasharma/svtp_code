<script setup>
import { ref } from 'vue';
import AppLayout from '../Layouts/AppLayout.vue';
import HeroCarousel from '../Components/HeroCarousel.vue';
import DevotionalDivider from '../Components/DevotionalDivider.vue';
import SearchWidget from '../Components/SearchWidget.vue';
import PackageCard from '../Components/PackageCard.vue';
import StarRating from '../Components/StarRating.vue';
import ShlokaTicker from '../Components/ShlokaTicker.vue';
import TrustBadgeMarquee from '../Components/TrustBadgeMarquee.vue';
import { appUrl } from '../appUrl';
import DirectorMessage from '../Components/DirectorMessage.vue';
import {
    whyChooseUs, attractions, bestTimeToVisit, galleryImages,
    blogPosts, faqItems, contactInfo,
} from '../festiveAssets';

defineProps({
    featured: { type: Array, default: () => [] },
    banners: { type: Array, default: () => [] },
    destinations: { type: Array, default: () => [] },
    testimonials: { type: Array, default: () => [] },
});

const circuits = [
    { name: 'Braj — Vrindavan & Mathura', note: 'Gokul, Nandgaon, Barsana, Govardhan', icon: 'bi-flower3' },
    { name: 'Uttar Pradesh Circuit', note: 'Ayodhya, Varanasi, Prayagraj', icon: 'bi-bank2' },
    { name: 'Rajasthan Circuit', note: 'Khatu Shyam Ji, Salasar Balaji', icon: 'bi-building' },
    { name: 'Uttarakhand Circuit', note: 'Haridwar, Rishikesh', icon: 'bi-water' },
];

const services = [
    { icon: 'bi-car-front-fill', title: 'Private Taxi & Vehicles', text: 'Sedans to tempo travellers — sanitised, AC, driven by local Braj drivers.' },
    { icon: 'bi-building-fill', title: 'Hotel Assistance', text: 'Budget to 4-star stays near your darshan route, booked on your behalf.' },
    { icon: 'bi-person-badge-fill', title: 'Local & English-Speaking Guides', text: 'Guides who know temple timings, crowd flow and the stories behind each site.' },
];

const openFaq = ref(0);
function toggleFaq(i) { openFaq.value = openFaq.value === i ? -1 : i; }
</script>

<template>
    <AppLayout>
        <HeroCarousel :banners="banners">
            <template #search>
                <SearchWidget :destinations="destinations" />
            </template>
        </HeroCarousel>
        <DevotionalDivider />

        <!-- Circuit chips -->
        <section class="container py-5 mt-4">
            <div class="d-flex flex-wrap gap-3 justify-content-center">
                <div v-for="(c, i) in circuits" :key="c.name" class="category-chip fade-in-up" :class="`delay-${i + 1}`">
                    <i class="bi" :class="c.icon"></i>
                    <strong>{{ c.name }}</strong>
                    <span class="text-muted">— {{ c.note }}</span>
                </div>
            </div>
        </section>

        <ShlokaTicker />


        <DirectorMessage />
        <!-- Featured tours -->
        <section class="container py-5">
            <p class="section-eyebrow text-center">दर्शन यात्रा</p>
            <h2 class="section-title text-center mb-5">Popular Tour Packages</h2>
            <div class="row g-4">
                <div v-for="tour in featured" :key="tour.id" class="col-md-4">
                    <PackageCard :pkg="tour" />
                </div>
            </div>
            <div class="text-center mt-5">
                <a :href="appUrl('/packages')" class="btn btn-outline-svtp">Browse All Tours</a>
                <a href="https://wa.me/918923427393" target="_blank" rel="noopener" class="btn btn-svtp ms-2">
                    <i class="bi bi-whatsapp me-1"></i>Plan My Trip on WhatsApp
                </a>
                <a :href="contactInfo.phoneHref" class="btn btn-outline-svtp ms-2">
                    <i class="bi bi-telephone-fill me-1"></i>Call Now
                </a>
            </div>
        </section>

        <!-- Why choose us -->
        <section class="bg-cream-warm py-5">
            <div class="container">
                <p class="section-eyebrow text-center">हमारी सेवा</p>
                <h2 class="section-title text-center mb-5">Why Travel With Us?</h2>
                <div class="row g-4">
                    <div v-for="(w, i) in whyChooseUs" :key="w.title" class="col-md-3 col-6">
                        <div class="text-center fade-in-up" :class="`delay-${i + 1}`">
                            <div class="mx-auto mb-3 d-flex align-items-center justify-content-center"
                                 style="width:64px;height:64px;border-radius:50%;background:var(--festive-gradient);">
                                <i class="bi" :class="w.icon" style="font-size:1.6rem;color:#fff;"></i>
                            </div>
                            <h6 class="text-svtp">{{ w.title }}</h6>
                            <p class="small text-muted">{{ w.text }}</p>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Taxi / hotel / guide services strip -->
        <section class="container py-5">
            <p class="section-eyebrow text-center">सेवाएं</p>
            <h2 class="section-title text-center mb-5">Everything You Need for Your Braj Journey</h2>
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

        <!-- Trust strip -->
        <section class="trust-strip py-5">
            <div class="container d-flex flex-wrap justify-content-around text-center gap-3">
                <div class="trust-item fade-in-up"><h3>50,000+</h3><small>Pilgrims Guided</small></div>
                <div class="diya-divider d-none d-md-block"></div>
                <div class="trust-item fade-in-up delay-1"><h3>4.8 / 5</h3><small>Google Rating</small></div>
                <div class="diya-divider d-none d-md-block"></div>
                <div class="trust-item fade-in-up delay-2"><h3>100%</h3><small>Transparent Pricing</small></div>
                <div class="diya-divider d-none d-md-block"></div>
                <div class="trust-item fade-in-up delay-3"><h3>24 / 7</h3><small>WhatsApp Support</small></div>
            </div>
        </section>

        <!-- Explore Braj / must-visit attractions -->
        <section class="container py-5">
            <p class="section-eyebrow text-center">प्रमुख स्थल</p>
            <h2 class="section-title text-center mb-2">Explore Braj — Must-Visit Places</h2>
            <p class="text-muted text-center mb-5">A quick look at the sites every darshan route revolves around.</p>
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
            <div class="text-center mt-4">
                <a :href="appUrl('/destinations')" class="btn btn-outline-svtp">Explore All Destinations</a>
            </div>
        </section>

        <!-- Best time to visit -->
        <section class="bg-cream-warm py-5">
            <div class="container">
                <p class="section-eyebrow text-center">यात्रा समय</p>
                <h2 class="section-title text-center mb-5">Best Time to Visit</h2>
                <div class="row justify-content-center">
                    <div class="col-lg-9">
                        <div class="glass-card p-0 overflow-hidden">
                            <table class="table mb-0">
                                <thead>
                                    <tr class="text-svtp">
                                        <th>Period</th><th>Weather</th><th>Best For</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="b in bestTimeToVisit" :key="b.period">
                                        <td class="fw-semibold">{{ b.period }}</td>
                                        <td>{{ b.weather }}</td>
                                        <td class="text-muted">{{ b.goodFor }}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Gallery preview -->
        <section class="container py-5">
            <p class="section-eyebrow text-center">फोटो गैलरी</p>
            <h2 class="section-title text-center mb-2">Moments From Braj</h2>
            <p class="text-muted text-center mb-5">A glimpse of the temples, ghats and festivals your journey will include.</p>
            <div class="gallery-grid">
                <a v-for="(img, i) in galleryImages" :key="i" :href="appUrl('/gallery')">
                    <img :src="img" :alt="`Braj gallery photo ${i + 1}`" loading="lazy" />
                </a>
            </div>
        </section>

        <!-- Testimonials -->
        <section v-if="testimonials.length" class="bg-cream-warm py-5">
            <div class="container">
                <p class="section-eyebrow text-center">भक्तों के अनुभव</p>
                <h2 class="section-title text-center mb-5">What Our Devotees Say</h2>
                <div class="row g-4">
                    <div v-for="t in testimonials" :key="t.id" class="col-md-4">
                        <div class="testimonial-card">
                            <p class="testimonial-mark mb-1">॥</p>
                            <StarRating :rating="t.rating" />
                            <p class="mt-2 mb-3">{{ t.comment }}</p>
                            <p class="fw-semibold mb-0">— {{ t.user?.name }}</p>
                            <small class="text-muted">{{ t.package?.title }}</small>
                        </div>
                    </div>
                </div>
                <div class="text-center mt-4">
                    <a :href="appUrl('/testimonials')" class="btn btn-outline-svtp">Read More Reviews</a>
                </div>
            </div>
        </section>

        <!-- Blog preview -->
        <section class="container py-5">
            <p class="section-eyebrow text-center">यात्रा गाइड</p>
            <h2 class="section-title text-center mb-5">From the Travel Blog</h2>
            <div class="row g-4">
                <div v-for="post in blogPosts" :key="post.title" class="col-md-4">
                    <div class="blog-card">
                        <img :src="post.image" :alt="post.title" loading="lazy" />
                        <div class="blog-card-body">
                            <h6 class="text-svtp">{{ post.title }}</h6>
                            <p class="small text-muted">{{ post.excerpt }}</p>
                            <small class="text-muted fst-italic">{{ post.date }}</small>
                        </div>
                    </div>
                </div>
            </div>
            <div class="text-center mt-4">
                <a :href="appUrl('/blog')" class="btn btn-outline-svtp">Read All Guides</a>
            </div>
        </section>

        <!-- FAQ -->
        <section class="bg-cream-warm py-5">
            <div class="container">
                <p class="section-eyebrow text-center">प्रश्न</p>
                <h2 class="section-title text-center mb-5">Frequently Asked Questions</h2>
                <div class="row justify-content-center">
                    <div class="col-lg-8">
                        <div v-for="(f, i) in faqItems" :key="f.q" class="faq-item" @click="toggleFaq(i)">
                            <div class="faq-q">
                                <span>{{ f.q }}</span>
                                <i class="bi" :class="openFaq === i ? 'bi-dash-circle-fill' : 'bi-plus-circle-fill'" style="color: var(--gulal);"></i>
                            </div>
                            <p v-if="openFaq === i" class="faq-a">{{ f.a }}</p>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <section class="container py-5">
            <p class="section-eyebrow text-center">संपर्क करें</p>
            <h2 class="section-title text-center mb-5">Contact Our Braj Travel Team</h2>
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

        <!-- Trust badge marquee -->
        <section class="py-4">
            <TrustBadgeMarquee />
        </section>
    </AppLayout>
</template>
