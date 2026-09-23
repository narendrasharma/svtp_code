<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import { appUrl } from '../../../appUrl';
import { useLocalization } from '../../../i18n';
import GlobalSearch from '../../GlobalSearch.vue';
import PublicBrand from './PublicBrand.vue';
import CurrencySwitcher from '../../CurrencySwitcher.vue';
import LanguageSwitcher from '../../LanguageSwitcher.vue';

const props = defineProps({
    mode: {
        type: String,
        default: 'solid',
        validator: (value) => ['solid', 'transparent'].includes(value),
    },
});

const emit = defineEmits(['enquiry']);
const page = usePage();
const { t } = useLocalization();
const isMobileOpen = ref(false);

const modules = computed(() => page.props.platformModules ?? []);
const authUser = computed(() => page.props.auth?.user ?? null);
const currentPath = computed(() => page.url.split('?')[0]);

function moduleEnabled(key) {
    const module = modules.value.find((item) => item.key === key);
    return module ? !!module.enabled : key === 'tours' || key === 'taxi';
}

const navigation = computed(() => [
    moduleEnabled('hotels') ? { key: 'hotels', label: t('navigation.hotels', 'Hotels'), href: '/hotels' } : null,
    moduleEnabled('tours') ? { key: 'tours', label: t('navigation.tours', 'Tours'), href: '/search/tours' } : null,
    moduleEnabled('taxi') ? { key: 'taxi', label: t('navigation.taxi', 'Taxi'), href: '/taxi' } : null,
    { key: 'destinations', label: t('navigation.destinations', 'Destinations'), href: '/destinations' },
    { key: 'about', label: t('navigation.about', 'About'), href: '/about' },
].filter(Boolean));

const accountLink = computed(() => authUser.value
    ? { label: t('navigation.account', 'My Account'), href: '/account' }
    : { label: t('navigation.sign_in', 'Sign in'), href: '/login' });

function isActive(link) {
    return currentPath.value === link.href || currentPath.value.startsWith(link.href + '/');
}

function closeMobile() {
    isMobileOpen.value = false;
}

function onKeydown(event) {
    if (event.key === 'Escape') {
        closeMobile();
    }
}

watch(isMobileOpen, (open) => {
    if (window.innerWidth <= 900) {
        document.body.style.overflow = open ? 'hidden' : '';
    }
});

onMounted(() => window.addEventListener('keydown', onKeydown));
onBeforeUnmount(() => {
    window.removeEventListener('keydown', onKeydown);
    document.body.style.overflow = '';
});
</script>

<template>
    <header class="public-header" :class="{ 'public-header--transparent': props.mode === 'transparent' }">
        <div class="public-container public-header__inner">
            <PublicBrand />

            <nav class="public-header__nav" aria-label="Primary navigation">
                <ul class="public-nav">
                    <li v-for="link in navigation" :key="link.key">
                        <Link
                            :href="appUrl(link.href)"
                            class="public-nav__link"
                            :class="{ 'is-active': isActive(link) }"
                            :aria-current="isActive(link) ? 'page' : undefined"
                        >
                            {{ link.label }}
                        </Link>
                    </li>
                </ul>
            </nav>

            <div class="public-header__tools">
                <GlobalSearch />
                <LanguageSwitcher />
                <CurrencySwitcher />
                <Link :href="appUrl(accountLink.href)" class="public-button public-button--outline public-button--sm">
                    <i class="bi bi-person" aria-hidden="true"></i>
                    {{ accountLink.label }}
                </Link>
                <button type="button" class="public-button public-button--primary public-button--sm" @click="emit('enquiry')">
                    {{ t('common.plan_a_trip', 'Plan a trip') }}
                </button>
                <button
                    type="button"
                    class="public-icon-button public-mobile-toggle"
                    aria-controls="public-mobile-navigation"
                    :aria-expanded="isMobileOpen"
                    :aria-label="t('common.menu', 'Menu')"
                    @click="isMobileOpen = !isMobileOpen"
                >
                    <i class="bi" :class="isMobileOpen ? 'bi-x-lg' : 'bi-list'" aria-hidden="true"></i>
                </button>
            </div>
        </div>

        <div v-if="isMobileOpen" id="public-mobile-navigation" class="public-mobile-panel public-container">
            <nav aria-label="Mobile navigation">
                <Link
                    v-for="link in navigation"
                    :key="link.key"
                    :href="appUrl(link.href)"
                    class="public-mobile-panel__link"
                    :class="{ 'is-active': isActive(link) }"
                    :aria-current="isActive(link) ? 'page' : undefined"
                    @click="closeMobile"
                >
                    {{ link.label }}
                    <i class="bi bi-arrow-up-right" data-dir-icon="arrow" aria-hidden="true"></i>
                </Link>
                <Link :href="appUrl(accountLink.href)" class="public-mobile-panel__link" @click="closeMobile">
                    {{ accountLink.label }}
                    <i class="bi bi-arrow-up-right" data-dir-icon="arrow" aria-hidden="true"></i>
                </Link>
            </nav>
            <div class="public-mobile-panel__tools">
                <LanguageSwitcher />
                <CurrencySwitcher />
                <button type="button" class="public-button public-button--primary public-button--lg" @click="closeMobile(); emit('enquiry')">
                    {{ t('common.plan_a_trip', 'Plan a trip') }}
                </button>
            </div>
        </div>
    </header>
</template>
