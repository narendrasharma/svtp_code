<script setup>
const whatsappNumber = '918923427393';
const message = encodeURIComponent('Radhe Radhe! I would like to know more about your Vrindavan tour packages.');
</script>

<template>
    <a
        :href="`https://wa.me/${whatsappNumber}?text=${message}`"
        target="_blank"
        rel="noopener"
        class="whatsapp-float"
        aria-label="Chat on WhatsApp"
    >
        <i class="bi bi-whatsapp"></i>
        <span class="visually-hidden">WhatsApp</span>
    </a>
</template>
