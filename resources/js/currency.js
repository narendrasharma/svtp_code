import { computed } from 'vue';
import { usePage, router } from '@inertiajs/vue3';
import { appUrl } from './appUrl';

/**
 * Shared display-currency foundation (Phase 13B).
 *
 * DISPLAY ONLY: the frontend formats server-provided amounts and
 * display conversions. Authoritative math (quotes, totals, refunds)
 * always happens server-side — never parseFloat/toFixed here.
 */
export function useCurrency() {
    const page = usePage();

    const contract = computed(() => page.props.currency ?? {
        selected: 'USD',
        default: 'USD',
        currencies: [],
    });

    const locale = computed(() => page.props.localization?.locale ?? 'en');
    const selected = computed(() => contract.value.selected ?? contract.value.default ?? 'USD');
    const defaultCurrency = computed(() => contract.value.default ?? 'USD');
    const currencies = computed(() => contract.value.currencies ?? []);

    /**
     * Format an amount in its OWN currency (no conversion).
     */
    function formatMoney(amount, code) {
        const numeric = Number(amount);
        if (!Number.isFinite(numeric)) return '';

        try {
            return new Intl.NumberFormat(locale.value, {
                style: 'currency',
                currency: code,
            }).format(numeric);
        } catch (e) {
            return `${code} ${numeric.toFixed(2)}`;
        }
    }

    /**
     * Render a server-provided MoneyPresenter DTO:
     * { formatted, display_formatted, conversion_applied, ... }
     */
    function renderMoney(dto) {
        if (!dto) return '';
        if (dto.conversion_applied && dto.display_formatted && dto.display_formatted !== dto.formatted) {
            return `${dto.display_formatted} (approx.)`;
        }
        return dto.formatted ?? '';
    }

    function switchCurrency(code) {
        router.post(appUrl('/currency'), { currency: code }, { preserveScroll: true });
    }

    return { contract, locale, selected, defaultCurrency, currencies, formatMoney, renderMoney, switchCurrency };
}
