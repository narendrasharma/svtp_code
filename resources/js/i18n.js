import { computed } from 'vue';
import { usePage, router } from '@inertiajs/vue3';
import { appUrl } from './appUrl';

/**
 * Shared localization foundation (Phase 13A, marketplace-ready).
 *
 * No vue-i18n dependency by design: the platform shares only the
 * current-locale `localeStrings` prop (common/navigation/booking
 * domains) plus the `localization` contract. Full copy conversion
 * arrives with the fresh marketplace frontend.
 */
export function useLocalization() {
    const page = usePage();

    const localization = computed(() => page.props.localization ?? {
        locale: 'en',
        direction: 'ltr',
        default_locale: 'en',
        languages: [],
    });

    const locale = computed(() => localization.value.locale ?? 'en');
    const direction = computed(() => localization.value.direction ?? 'ltr');
    const defaultLocale = computed(() => localization.value.default_locale ?? 'en');
    const languages = computed(() => localization.value.languages ?? []);
    const strings = computed(() => page.props.localeStrings ?? {});

    function t(key, fallback = null) {
        if (strings.value[key] !== undefined) return strings.value[key];
        return fallback ?? key;
    }

    function isRtl() {
        return direction.value === 'rtl';
    }

    function switchLocale(nextLocale) {
        router.post(appUrl('/locale'), { locale: nextLocale }, { preserveScroll: true });
    }

    return { localization, locale, direction, defaultLocale, languages, strings, t, isRtl, switchLocale };
}

/**
 * Direction-aware icon class: pass the LTR icon (e.g. `bi-arrow-right`)
 * and get the mirrored RTL equivalent for directional icons only.
 * Never mirror logos, photos, maps or non-directional icons.
 */
export function directionalIcon(ltrIcon) {
    const map = {
        'bi-arrow-right': 'bi-arrow-left',
        'bi-arrow-left': 'bi-arrow-right',
        'bi-chevron-right': 'bi-chevron-left',
        'bi-chevron-left': 'bi-chevron-right',
        'bi-caret-right': 'bi-caret-left',
        'bi-caret-left': 'bi-caret-right',
    };
    return map[ltrIcon] ?? ltrIcon;
}
