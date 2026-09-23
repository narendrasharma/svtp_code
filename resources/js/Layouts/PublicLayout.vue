<script setup>
import { ref, watch } from 'vue';
import { usePage } from '@inertiajs/vue3';
import { useLocalization } from '../i18n';
import ImpersonationBanner from '../Components/ImpersonationBanner.vue';
import PromotionalPopupModal from '../Components/PromotionalPopupModal.vue';
import QuickEnquiryModal from '../Components/QuickEnquiryModal.vue';
import TourPlanEnquiryModal from '../Components/TourPlanEnquiryModal.vue';
import WhatsAppFloat from '../Components/WhatsAppFloatButton.vue';
import PublicFooter from '../Components/Public/Layout/PublicFooter.vue';
import PublicHeader from '../Components/Public/Layout/PublicHeader.vue';

const props = defineProps({
    headerMode: {
        type: String,
        default: 'solid',
        validator: (value) => ['solid', 'transparent'].includes(value),
    },
    mainClass: {
        type: String,
        default: '',
    },
    showFloatingActions: {
        type: Boolean,
        default: true,
    },
});

const page = usePage();
const { direction, locale } = useLocalization();
const isQuickEnquiryOpen = ref(false);
const isTourEnquiryOpen = ref(false);

watch([direction, locale], ([nextDirection, nextLocale]) => {
    document.documentElement.setAttribute('dir', nextDirection || 'ltr');
    document.documentElement.setAttribute('lang', nextLocale || 'en');
}, { immediate: true });
</script>

<template>
    <div class="public-shell" :dir="direction" :data-public-direction="direction">
        <ImpersonationBanner />
        <PublicHeader :mode="props.headerMode" @enquiry="isTourEnquiryOpen = true" />

        <div v-if="page.props.flash?.message" class="public-notice public-notice--success" role="status">
            {{ page.props.flash.message }}
        </div>

        <main class="public-main" :class="props.mainClass">
            <slot />
        </main>

        <PublicFooter />

        <button v-if="props.showFloatingActions" type="button" class="public-quick-enquiry" @click="isQuickEnquiryOpen = true">
            <i class="bi bi-chat-dots" aria-hidden="true"></i>
            <span>Enquiry</span>
        </button>
        <WhatsAppFloat v-if="props.showFloatingActions" />
        <PromotionalPopupModal :popup="page.props.promotionalPopup" />
        <QuickEnquiryModal :open="isQuickEnquiryOpen" @close="isQuickEnquiryOpen = false" />
        <TourPlanEnquiryModal
            :open="isTourEnquiryOpen"
            :security-question="page.props.securityQuestion"
            @close="isTourEnquiryOpen = false"
        />
    </div>
</template>
