<script setup>
import { trustBadges } from '../festiveAssets';

// Duplicated for a seamless CSS marquee loop.
const items = [...trustBadges, ...trustBadges];
</script>

<template>
    <div class="marquee-strip">
        <div class="marquee-strip-track">
            <div v-for="(b, i) in items" :key="i" class="marquee-badge">
                <i class="bi" :class="b.icon"></i>
                <span>{{ b.label }}</span>
            </div>
        </div>
    </div>
</template>
