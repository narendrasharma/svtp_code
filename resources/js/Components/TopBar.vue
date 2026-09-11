<script setup>
import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';

const page = usePage();

const settings = computed(() => page.props.siteSettings ?? {});

const phoneHref = computed(() => {
    const phone = settings.value.primary_phone;

    if (!phone) {
        return null;
    }

    const cleaned = phone.replace(/[^\d+]/g, '');

    return `tel:${cleaned}`;
});

const socialLinks = computed(() => {
    const links = [];

    if (settings.value.facebook_url) {
        links.push({
            icon: 'bi-facebook',
            href: settings.value.facebook_url,
            label: 'Facebook',
        });
    }

    if (settings.value.instagram_url) {
        links.push({
            icon: 'bi-instagram',
            href: settings.value.instagram_url,
            label: 'Instagram',
        });
    }

    if (settings.value.youtube_url) {
        links.push({
            icon: 'bi-youtube',
            href: settings.value.youtube_url,
            label: 'YouTube',
        });
    }

    if (settings.value.whatsapp_number) {
        const number =
            settings.value.whatsapp_number.replace(/\D/g, '');

        links.push({
            icon: 'bi-whatsapp',
            href: `https://wa.me/${number}`,
            label: 'WhatsApp',
        });
    }

    return links;
});
</script>

<template>
    <div class="top-bar">
        <div
            class="container d-flex align-items-center justify-content-between"
        >
            <div class="top-bar-contact">

                <a
                    v-if="settings.primary_phone"
                    :href="phoneHref"
                >
                    <i class="bi bi-telephone-fill"></i>

                    {{ settings.primary_phone }}
                </a>


                <a
                    v-if="settings.contact_email"
                    :href="`mailto:${settings.contact_email}`"
                    class="d-none d-sm-inline"
                >
                    <i class="bi bi-envelope-fill"></i>

                    {{ settings.contact_email }}
                </a>


                <a
                    v-if="
                        settings.google_maps_url &&
                        settings.office_address
                    "
                    :href="settings.google_maps_url"
                    target="_blank"
                    rel="noopener"
                    class="d-none d-lg-inline"
                >
                    <i class="bi bi-geo-alt-fill"></i>

                    {{ settings.office_address }}
                </a>

            </div>


            <div class="top-bar-social">

                <a
                    v-for="social in socialLinks"
                    :key="social.label"
                    :href="social.href"
                    target="_blank"
                    rel="noopener"
                    :aria-label="social.label"
                >
                    <i
                        class="bi"
                        :class="social.icon"
                    ></i>
                </a>

            </div>
        </div>
    </div>
</template>
