<script setup>
import { ref } from 'vue';
import { faqItems } from '../../festiveAssets';

defineProps({
    settings: { type: Object, default: () => ({}) },
});

const openFaq = ref(0);
function toggleFaq(i) { openFaq.value = openFaq.value === i ? -1 : i; }
</script>

<template>
    <section class="bg-cream-warm py-5">
        <div class="container">
            <p class="section-eyebrow text-center">प्रश्न</p>
            <h2 class="section-title text-center mb-5">{{ settings.heading || 'Frequently Asked Questions' }}</h2>
            <div class="row justify-content-center">
                <div class="col-lg-8">
                    <div v-for="(f, i) in faqItems" :key="f.q" class="faq-item" @click="toggleFaq(i)">
                        <div class="faq-q">
                            <span>{{ f.q }}</span>
                            <i class="bi" :class="openFaq === i ? 'bi-dash-circle-fill' : 'bi-plus-circle-fill'" style="color: var(--gulal);"></i>
                        </div>
                        <p v-if="openFaq === i" class="faq-a">{{ f.a }}</p>
                    </div>
                </div>
            </div>
        </div>
    </section>
</template>
