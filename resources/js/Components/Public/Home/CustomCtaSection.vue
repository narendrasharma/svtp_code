<script setup>
import Container from '../Layout/Container.vue';
import { safeHref } from '../homepage';
import { useLocalization } from '../../../i18n';

defineProps({ section: { type: Object, required: true } });
const { t } = useLocalization();
</script>

<template>
    <section class="homepage-cta-section">
        <Container>
            <div class="homepage-cta-panel">
                <div>
                    <span class="public-eyebrow homepage-cta-panel__eyebrow">{{ t('common.curated_for_you', 'Curated for you') }}</span>
                    <h2>{{ section.title || t('common.plan_your_next_escape', 'Plan your next escape') }}</h2>
                    <p v-if="section.subtitle">{{ section.subtitle }}</p>
                </div>
                <a v-if="safeHref(section.configuration?.cta_url)" :href="safeHref(section.configuration.cta_url)" class="public-button public-button--secondary public-button--lg">
                    {{ section.configuration.cta_label || t('common.explore_trips', 'Explore trips') }}
                    <i class="bi bi-arrow-up-right" data-dir-icon="arrow" aria-hidden="true"></i>
                </a>
            </div>
        </Container>
    </section>
</template>
