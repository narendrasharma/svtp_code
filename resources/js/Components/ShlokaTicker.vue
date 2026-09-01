<script setup>
import { dailyShlokas } from '../festiveAssets';

// Duplicate the list so the marquee loop is seamless.
const items = [...dailyShlokas, ...dailyShlokas];
</script>

<template>
    <div class="shloka-ticker">
        <div class="shloka-ticker-track">
            <div v-for="(s, i) in items" :key="i" class="shloka-ticker-item">
                <span>{{ s.sanskrit }}</span>
                <span class="dot">— {{ s.source }}</span>
                <span class="dot">॥</span>
            </div>
        </div>
    </div>
</template>
