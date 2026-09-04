<script setup>
import { onBeforeUnmount, onMounted, ref, watch } from 'vue';

const props = defineProps({ popup: { type: Object, default: null } });
const isOpen = ref(false);
const dismissalKey = `promotional-popup-dismissed-${props.popup?.id ?? 'none'}`;

function close() {
    isOpen.value = false;
    sessionStorage.setItem(dismissalKey, '1');
}

function handleKeydown(event) {
    if (event.key === 'Escape' && isOpen.value) {
        close();
    }
}

watch(isOpen, (open) => {
    document.body.style.overflow = open ? 'hidden' : '';
});

onMounted(() => {
    isOpen.value = Boolean(props.popup && ! sessionStorage.getItem(dismissalKey));
    window.addEventListener('keydown', handleKeydown);
});

onBeforeUnmount(() => {
    document.body.style.overflow = '';
    window.removeEventListener('keydown', handleKeydown);
});
</script>

<template>
    <div v-if="popup && isOpen" class="promotion-backdrop" role="presentation" @click.self="close">
        <section class="promotion-modal" role="dialog" aria-modal="true" :aria-label="popup.title || 'Special promotion'">
            <button type="button" class="promotion-close" aria-label="Close promotion" @click="close"><i class="bi bi-x-lg"></i></button>
            <a v-if="popup.cta_url" :href="popup.cta_url" class="promotion-image-link">
                <img :src="popup.image_url" :alt="popup.title || 'Special promotion'">
            </a>
            <img v-else :src="popup.image_url" :alt="popup.title || 'Special promotion'">
            <div v-if="popup.title || popup.cta_url" class="promotion-content">
                <h2 v-if="popup.title">{{ popup.title }}</h2>
                <a v-if="popup.cta_url" :href="popup.cta_url" class="btn btn-svtp">View Offer</a>
            </div>
        </section>
    </div>
</template>

<style scoped>
.promotion-backdrop { position: fixed; inset: 0; z-index: 2000; display: grid; place-items: center; padding: 1rem; background: rgba(20,6,14,.78); backdrop-filter: blur(5px); }.promotion-modal { position: relative; width: min(92vw,720px); max-height: calc(100dvh - 2rem); overflow: auto; border: 1px solid rgba(228,205,140,.7); border-radius: 1.25rem; background: #fff8f0; box-shadow: 0 28px 80px rgba(0,0,0,.42); }.promotion-modal > img,.promotion-image-link,.promotion-image-link img { display: block; width: 100%; }.promotion-modal > img,.promotion-image-link img { max-height: min(68vh,600px); object-fit: contain; background: #f8ecdc; }.promotion-image-link { text-decoration: none; }.promotion-close { position: absolute; top: .75rem; right: .75rem; z-index: 1; display: grid; width: 2.5rem; height: 2.5rem; place-items: center; border: 1px solid rgba(255,255,255,.75); border-radius: 50%; background: rgba(36,6,19,.84); color: #fff; box-shadow: 0 4px 14px rgba(0,0,0,.2); }.promotion-content { display: flex; align-items: center; justify-content: space-between; gap: 1rem; padding: 1rem 1.25rem 1.25rem; }.promotion-content h2 { margin: 0; font-size: clamp(1.35rem,4vw,2rem); }.promotion-content .btn { flex: 0 0 auto; }
@media (max-width: 575.98px) { .promotion-backdrop { align-items: center; padding: .65rem; }.promotion-modal { width: 100%; max-height: calc(100dvh - 1.3rem); border-radius: 1rem; }.promotion-content { flex-direction: column; align-items: stretch; text-align: center; }.promotion-content .btn { width: 100%; }.promotion-close { top: .5rem; right: .5rem; } }
</style>
