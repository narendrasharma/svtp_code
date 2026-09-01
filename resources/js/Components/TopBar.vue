<script setup>
import { contactInfo, socialLinks } from '../festiveAssets';
</script>

<template>
    <div class="top-bar">
        <div class="container d-flex align-items-center justify-content-between">
            <div class="top-bar-contact">
                <a :href="contactInfo.phoneHref"><i class="bi bi-telephone-fill"></i> {{ contactInfo.phone }}</a>
                <a :href="`mailto:${contactInfo.email}`" class="d-none d-sm-inline"><i class="bi bi-envelope-fill"></i> {{ contactInfo.email }}</a>
                <span class="d-none d-md-inline top-bar-hours"><i class="bi bi-clock-fill"></i> {{ contactInfo.hours }}</span>
            </div>
            <div class="top-bar-social">
                <a v-for="s in socialLinks" :key="s.label" :href="s.href" target="_blank" rel="noopener" :aria-label="s.label">
                    <i class="bi" :class="s.icon"></i>
                </a>
            </div>
        </div>
    </div>
</template>
