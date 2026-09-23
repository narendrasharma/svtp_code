<script setup>
import { computed } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import { appUrl } from '../../../appUrl';
import { useLocalization } from '../../../i18n';
import PublicBrand from './PublicBrand.vue';

const page = usePage();
const { t } = useLocalization();
const settings = computed(() => page.props.siteSettings ?? {});
const modules = computed(() => page.props.platformModules ?? []);
const year = new Date().getFullYear();

function moduleEnabled(key) {
    return modules.value.find((item) => item.key === key)?.enabled ?? (key === 'tours' || key === 'taxi');
}

const socialLinks = computed(() => [
    settings.value.facebook_url ? { href: settings.value.facebook_url, icon: 'bi-facebook', label: 'Facebook' } : null,
    settings.value.instagram_url ? { href: settings.value.instagram_url, icon: 'bi-instagram', label: 'Instagram' } : null,
    settings.value.youtube_url ? { href: settings.value.youtube_url, icon: 'bi-youtube', label: 'YouTube' } : null,
    settings.value.whatsapp_number ? {
        href: 'https://wa.me/' + settings.value.whatsapp_number.replace(/\D/g, ''),
        icon: 'bi-whatsapp',
        label: 'WhatsApp',
    } : null,
].filter(Boolean));
</script>

<template>
    <footer class="public-footer">
        <div class="public-container public-footer__main">
            <div class="public-footer__grid">
                <div class="public-footer__brand">
                    <PublicBrand />
                    <p v-if="settings.site_tagline" class="mt-3 mb-0">{{ settings.site_tagline }}</p>
                    <p v-if="settings.contact_email || settings.primary_phone" class="mt-3 mb-0">
                        <a v-if="settings.contact_email" :href="'mailto:' + settings.contact_email">{{ settings.contact_email }}</a>
                        <span v-if="settings.contact_email && settings.primary_phone" aria-hidden="true"> · </span>
                        <a v-if="settings.primary_phone" :href="'tel:' + settings.primary_phone.replace(/[^\d+]/g, '')">{{ settings.primary_phone }}</a>
                    </p>
                    <div v-if="socialLinks.length" class="public-footer__socials">
                        <a
                            v-for="social in socialLinks"
                            :key="social.label"
                            :href="social.href"
                            class="public-footer__social"
                            :aria-label="social.label"
                            target="_blank"
                            rel="noopener"
                        >
                            <i class="bi" :class="social.icon" aria-hidden="true"></i>
                        </a>
                    </div>
                </div>

                <div>
                    <h2 class="public-footer__title">Explore</h2>
                    <ul class="public-footer__links">
                        <li v-if="moduleEnabled('tours')"><Link :href="appUrl('/destinations')">{{ t('navigation.destinations', 'Destinations') }}</Link></li>
                        <li><Link :href="appUrl('/blog')">Blog</Link></li>
                        <li><Link :href="appUrl('/faq')">FAQs</Link></li>
                    </ul>
                </div>

                <div>
                    <h2 class="public-footer__title">Travel</h2>
                    <ul class="public-footer__links">
                        <li v-if="moduleEnabled('hotels')"><Link :href="appUrl('/hotels')">{{ t('navigation.hotels', 'Hotels') }}</Link></li>
                        <li v-if="moduleEnabled('tours')"><Link :href="appUrl('/search/tours')">{{ t('navigation.tours', 'Tours') }}</Link></li>
                        <li v-if="moduleEnabled('taxi')"><Link :href="appUrl('/taxi')">{{ t('navigation.taxi', 'Taxi') }}</Link></li>
                    </ul>
                </div>

                <div>
                    <h2 class="public-footer__title">Company</h2>
                    <ul class="public-footer__links">
                        <li><Link :href="appUrl('/about')">{{ t('navigation.about', 'About') }}</Link></li>
                        <li><Link :href="appUrl('/contact')">{{ t('navigation.contact', 'Contact') }}</Link></li>
                        <li><Link :href="appUrl('/privacy')">Privacy</Link></li>
                        <li><Link :href="appUrl('/terms')">Terms</Link></li>
                    </ul>
                </div>
            </div>
        </div>
        <div class="public-footer__bottom">
            <div class="public-container d-flex flex-wrap justify-content-between gap-2">
                <span>© {{ year }} {{ settings.site_name || 'Travel marketplace' }}</span>
                <span>{{ settings.copyright_text || 'All rights reserved.' }}</span>
            </div>
        </div>
    </footer>
</template>
