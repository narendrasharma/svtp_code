<script setup>
import { ref, onMounted, onBeforeUnmount } from 'vue';
import PeacockDecor from './PeacockDecor.vue';
import { appUrl } from '../appUrl';

const props = defineProps({
    banners: {
        type: Array,
        default: () => [],
    },
});

const active = ref(0);
let timer = null;

const SLIDE_MS = 6000;

function goTo(i) {
    if (!props.banners.length) return;

    active.value =
        (i + props.banners.length) % props.banners.length;

    restart();
}

function next() {
    goTo(active.value + 1);
}

function prev() {
    goTo(active.value - 1);
}

function restart() {
    clearInterval(timer);

    if (props.banners.length <= 1) {
        return;
    }

    timer = setInterval(next, SLIDE_MS);
}

onMounted(restart);

onBeforeUnmount(() => {
    clearInterval(timer);
});
</script>
<template>
    <section v-if="banners.length" class="hero-slider">
        <PeacockDecor />

        <div
            v-for="(banner, i) in banners"
            :key="banner.id"
            class="hero-slide"
            :class="{ 'is-active': i === active }"
        >
            <img
                class="hero-slide-img"
                :src="`${appUrl('/storage')}/${banner.image_path}`"
                :alt="banner.title || 'Tour banner'"
                loading="eager"
            />

            <div class="hero-slide-overlay"></div>
        </div>

        <button
            v-if="banners.length > 1"
            class="hero-arrow prev"
            type="button"
            aria-label="Previous slide"
            @click="prev"
        >
            <i class="bi bi-chevron-left"></i>
        </button>

        <button
            v-if="banners.length > 1"
            class="hero-arrow next"
            type="button"
            aria-label="Next slide"
            @click="next"
        >
            <i class="bi bi-chevron-right"></i>
        </button>

        <div class="container hero-content">
            <div class="row">
                <div class="col-lg-7">

                    <transition name="fade" mode="out-in">
                        <div
                            v-if="banners[active]"
                            :key="banners[active].id"
                            class="fade-in-up"
                        >
                            <h1
                                v-if="banners[active].title"
                                class="hero-title"
                            >
                                {{ banners[active].title }}
                            </h1>

                            <p
                                v-if="banners[active].subtitle"
                                class="hero-subtitle"
                            >
                                {{ banners[active].subtitle }}
                            </p>

                            <a
                                v-if="banners[active].cta_label && banners[active].cta_link"
                                :href="banners[active].cta_link"
                                class="btn btn-svtp mt-3"
                            >
                                {{ banners[active].cta_label }}
                            </a>
                        </div>
                    </transition>
                </div>
            </div>

            <slot name="search" />

            <div
                v-if="banners.length > 1"
                class="hero-dots"
            >
                <button
                    v-for="(banner, i) in banners"
                    :key="banner.id"
                    class="hero-dot"
                    :class="{ 'is-active': i === active }"
                    type="button"
                    :aria-label="`Go to slide ${i + 1}`"
                    @click="goTo(i)"
                ></button>
            </div>
        </div>
    </section>
</template>
<style scoped>
.fade-enter-active, .fade-leave-active { transition: opacity 0.3s ease; }
.fade-enter-from, .fade-leave-to { opacity: 0; }
</style>
