<script setup>
import { whyChooseUs } from '../../festiveAssets';

defineProps({
    settings: { type: Object, default: () => ({}) },
});
</script>

<template>
    <section class="bg-cream-warm py-5">
        <div class="container">
            <p class="section-eyebrow text-center">हमारी सेवा</p>
            <h2 class="section-title text-center mb-5">{{ settings.heading || 'Why Travel With Us?' }}</h2>
            <div class="row g-4">
                <div v-for="(w, i) in whyChooseUs" :key="w.title" class="col-md-3 col-6">
                    <div class="text-center fade-in-up" :class="`delay-${i + 1}`">
                        <div class="mx-auto mb-3 d-flex align-items-center justify-content-center"
                             style="width:64px;height:64px;border-radius:50%;background:var(--festive-gradient);">
                            <i class="bi" :class="w.icon" style="font-size:1.6rem;color:#fff;"></i>
                        </div>
                        <h6 class="text-svtp">{{ w.title }}</h6>
                        <p class="small text-muted">{{ w.text }}</p>
                    </div>
                </div>
            </div>
        </div>
    </section>
</template>
