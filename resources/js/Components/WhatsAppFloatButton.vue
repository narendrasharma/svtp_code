<script setup>
import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';

const page = usePage();

const settings = computed(() => page.props.siteSettings ?? {});

const whatsappNumber = computed(() => {
    return settings.value.whatsapp_number
        ?.replace(/\D/g, '') || '';
});

const message = computed(() => {
    return encodeURIComponent(
        settings.value.whatsapp_message ||
        'Hello! I would like to know more about your tour packages.'
    );
});

const whatsappUrl = computed(() => {
    if (!whatsappNumber.value) {
        return null;
    }

    return `https://wa.me/${whatsappNumber.value}?text=${message.value}`;
});
</script>


<template>
    <a
        v-if="whatsappUrl"
        :href="whatsappUrl"
        target="_blank"
        rel="noopener"
        class="whatsapp-float"
        aria-label="Chat on WhatsApp"
    >
        <i class="bi bi-whatsapp"></i>

        <span class="visually-hidden">
            WhatsApp
        </span>
    </a>
</template>
