<script setup>
import { Head } from '@inertiajs/vue3';
import PublicLayout from './PublicLayout.vue';
import AccountNav from '../Components/AccountNav.vue';
import { useLocalization } from '../i18n';

const { t } = useLocalization();
</script>

<template>
    <PublicLayout main-class="account-page">
        <Head>
            <meta head-key="robots" name="robots" content="noindex,nofollow" />
            <meta head-key="referrer" name="referrer" content="no-referrer" />
        </Head>
        <div class="public-container account-container">
            <p class="public-eyebrow">{{ t('common.my_account', 'My account') }}</p>
            <AccountNav />
            <div class="account-content"><slot /></div>
        </div>
    </PublicLayout>
</template>

<style scoped>
:deep(.account-page) { background: var(--public-bg); }
.account-container { padding-block: clamp(1.5rem, 4vw, 3rem) 4rem; min-block-size: 65vh; }
.account-content { min-inline-size: 0; }
.account-content :deep(.container),
.account-content :deep(.container-fluid),
.account-content :deep(.hotel-customer-container),
.account-content :deep(.taxi-customer-shell) {
    width: 100%; max-width: none !important; margin-inline: 0; padding: 0 !important;
}
.account-content :deep(.tour-customer-page) { min-height: 0; background: transparent; }
.account-content :deep(h1) {
    max-width: none; margin-block: 0 1rem; color: var(--public-text);
    font-family: var(--public-font-display); font-size: clamp(1.8rem, 3.5vw, 2.8rem);
    line-height: 1.15; letter-spacing: normal; overflow-wrap: anywhere;
}
.account-content :deep(.hotel-customer-header),
.account-content :deep(.hotel-customer-detail-header),
.account-content :deep(.tour-customer-heading),
.account-content :deep(.tour-customer-detail__heading),
.account-content :deep(.taxi-customer-header) { margin-block: 0 2rem; }
.account-content :deep(.profile-card),
.account-content :deep(.glass-card),
.account-content :deep(.card) {
    background: var(--public-surface); border: 1px solid var(--public-border);
    border-radius: 1.25rem; box-shadow: none;
}
.account-content :deep(.pagination) { flex-wrap: wrap; gap: 0.4rem; padding-inline: 0; }
.account-content :deep(.page-link) { border-radius: 0.6rem; color: var(--public-brand); }
.account-content :deep(.active > .page-link) { background: var(--public-brand); border-color: var(--public-brand); color: #fff; }
.account-content :deep(:focus-visible) { outline: 3px solid var(--public-brand); outline-offset: 3px; }
.account-content :deep(.account-profile-content) { max-inline-size: 54rem; }
</style>
