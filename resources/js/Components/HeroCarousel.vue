<script setup>
import { ref, onMounted, onBeforeUnmount } from 'vue';
import { heroSlides } from '../festiveAssets';
import PeacockDecor from './PeacockDecor.vue';

const active = ref(0);
let timer = null;
const SLIDE_MS = 6000;

function goTo(i) {
    active.value = (i + heroSlides.length) % heroSlides.length;
    restart();
}
function next() { goTo(active.value + 1); }
function prev() { goTo(active.value - 1); }

function restart() {
    clearInterval(timer);
    timer = setInterval(next, SLIDE_MS);
}

onMounted(restart);
onBeforeUnmount(() => clearInterval(timer));
</script>

<template>
    <section class="hero-slider">
        <PeacockDecor />
        <div
            v-for="(slide, i) in heroSlides"
            :key="i"
            class="hero-slide"
            :class="{ 'is-active': i === active }"
        >
            <img class="hero-slide-img" :src="slide.image" :alt="slide.title" loading="eager" />
            <div class="hero-slide-overlay"></div>
        </div>

        <button class="hero-arrow prev" type="button" aria-label="Previous slide" @click="prev">
            <i class="bi bi-chevron-left"></i>
        </button>
        <button class="hero-arrow next" type="button" aria-label="Next slide" @click="next">
            <i class="bi bi-chevron-right"></i>
        </button>

        <div class="container hero-content">
            <div class="row">
                <div class="col-lg-7">
                    <transition name="fade" mode="out-in">
                        <div :key="active" class="fade-in-up">
                            <p class="hero-devanagari">{{ heroSlides[active].devanagari }}</p>
                            <h1 class="hero-title">{{ heroSlides[active].title }}</h1>
                            <p class="hero-subtitle">{{ heroSlides[active].subtitle }}</p>
                        </div>
                    </transition>
                </div>
            </div>

            <slot name="search" />

            <div class="hero-dots">
                <button
                    v-for="(slide, i) in heroSlides"
                    :key="i"
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
