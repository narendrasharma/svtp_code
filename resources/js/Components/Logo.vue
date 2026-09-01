<script setup>
defineProps({ compact: { type: Boolean, default: false } });
</script>

<template>
    <span class="svtp-logo">
        <svg class="svtp-logo-mark" viewBox="0 0 48 48" xmlns="http://www.w3.org/2000/svg">
            <defs>
                <linearGradient id="svtpLogoGrad" x1="0" y1="0" x2="1" y2="1">
                    <stop offset="0%" stop-color="#F97316" />
                    <stop offset="55%" stop-color="#EC4899" />
                    <stop offset="100%" stop-color="#F59E0B" />
                </linearGradient>
            </defs>
            <circle cx="24" cy="24" r="23" fill="url(#svtpLogoGrad)" opacity="0.12" />
            <!-- lotus petals -->
            <g fill="url(#svtpLogoGrad)">
                <path d="M24 34c-6-4-9-10-9-15 4 1 8 4 9 9 1-5 5-8 9-9 0 5-3 11-9 15z" />
                <path d="M24 34c-9-1-15-6-17-11 4-1 9 0 12 4 1-4 4-7 8-8-1 5-1 11-3 15z" opacity="0.85" />
                <path d="M24 34c9-1 15-6 17-11-4-1-9 0-12 4-1-4-4-7-8-8 1 5 1 11 3 15z" opacity="0.85" />
            </g>
            <circle cx="24" cy="30" r="3.4" fill="#6B1029" />
        </svg>
        <span v-if="!compact" class="svtp-logo-text">
            <span class="line1">Shree Vrindavan</span>
            <span class="line2">Tour Packages</span>
        </span>
    </span>
</template>
