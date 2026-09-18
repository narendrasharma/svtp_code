<script setup>
import { computed } from 'vue';
import AppLayout from '../Layouts/AppLayout.vue';
import SeoHead from '@/Components/SeoHead.vue';
import DevotionalDivider from '../Components/DevotionalDivider.vue';
import ShlokaTicker from '../Components/ShlokaTicker.vue';
import DirectorMessage from '../Components/DirectorMessage.vue';
import TrustBadgeMarquee from '../Components/TrustBadgeMarquee.vue';
import HomeHero from '../Components/Home/HomeHero.vue';
import HomeCircuits from '../Components/Home/HomeCircuits.vue';
import HomeFeaturedPackages from '../Components/Home/HomeFeaturedPackages.vue';
import HomeWhyChooseUs from '../Components/Home/HomeWhyChooseUs.vue';
import HomeServices from '../Components/Home/HomeServices.vue';
import HomeTrustStrip from '../Components/Home/HomeTrustStrip.vue';
import HomeAttractions from '../Components/Home/HomeAttractions.vue';
import HomeBestTime from '../Components/Home/HomeBestTime.vue';
import HomeGallery from '../Components/Home/HomeGallery.vue';
import HomeTestimonials from '../Components/Home/HomeTestimonials.vue';
import HomeBlog from '../Components/Home/HomeBlog.vue';
import HomeFaq from '../Components/Home/HomeFaq.vue';
import HomeContact from '../Components/Home/HomeContact.vue';

// Controlled whitelist: only these keys ever render, so unknown or removed
// configuration can never inject arbitrary components.
const SECTION_COMPONENTS = {
    hero: HomeHero,
    divider: DevotionalDivider,
    circuits: HomeCircuits,
    shloka_ticker: ShlokaTicker,
    director_message: DirectorMessage,
    featured_packages: HomeFeaturedPackages,
    why_choose_us: HomeWhyChooseUs,
    services: HomeServices,
    trust_strip: HomeTrustStrip,
    attractions: HomeAttractions,
    best_time: HomeBestTime,
    gallery: HomeGallery,
    testimonials: HomeTestimonials,
    blog: HomeBlog,
    faq: HomeFaq,
    contact: HomeContact,
    trust_marquee: TrustBadgeMarquee,
};

// Last-resort order when the backend sends no configuration at all.
const FALLBACK_ORDER = Object.keys(SECTION_COMPONENTS);

const props = defineProps({
    featured: { type: Array, default: () => [] },
    banners: { type: Array, default: () => [] },
    destinations: { type: Array, default: () => [] },
    testimonials: { type: Array, default: () => [] },
    homepageSections: { type: Array, default: () => [] },
});

const activeSections = computed(() => {
    const configured = props.homepageSections.length
        ? props.homepageSections
        : FALLBACK_ORDER.map(key => ({ key, settings: {} }));

    return configured.filter(section => SECTION_COMPONENTS[section.key]);
});

function sectionProps(section) {
    switch (section.key) {
        case 'hero':
            return { banners: props.banners, destinations: props.destinations, settings: section.settings ?? {} };
        case 'featured_packages':
            return { tours: props.featured, settings: section.settings ?? {} };
        case 'testimonials':
            return { testimonials: props.testimonials, settings: section.settings ?? {} };
        case 'why_choose_us':
        case 'services':
        case 'attractions':
        case 'best_time':
        case 'gallery':
        case 'blog':
        case 'faq':
        case 'contact':
            return { settings: section.settings ?? {} };
        default:
            return {};
    }
}
</script>

<template>
    <AppLayout>
        <SeoHead />
        <template v-for="section in activeSections" :key="section.key">
            <component :is="SECTION_COMPONENTS[section.key]" v-bind="sectionProps(section)" />
        </template>
    </AppLayout>
</template>
