<script setup>
import { appUrl } from '../../appUrl';
import AdminLayout from '../../Layouts/AdminLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { onBeforeUnmount, ref } from 'vue';

const props = defineProps({ promotionalPopup: { type: Object, default: null } });
const selectedImagePreview = ref(null);
const form = useForm({
    image_upload: null,
    remove_image: false,
    title: props.promotionalPopup?.title ?? '',
    cta_url: props.promotionalPopup?.cta_url ?? '',
    is_active: props.promotionalPopup?.is_active ?? false,
});

function selectImage(event) {
    if (selectedImagePreview.value) {
        URL.revokeObjectURL(selectedImagePreview.value);
    }

    form.image_upload = event.target.files?.[0] ?? null;
    selectedImagePreview.value = form.image_upload ? URL.createObjectURL(form.image_upload) : null;

    if (form.image_upload) {
        form.remove_image = false;
    }
}

function removeImage() {
    form.image_upload = null;
    form.remove_image = true;
    form.is_active = false;

    if (selectedImagePreview.value) {
        URL.revokeObjectURL(selectedImagePreview.value);
        selectedImagePreview.value = null;
    }
}

function submit() {
    form.post(appUrl('/admin/promotional-popup'), { forceFormData: true, preserveScroll: true });
}

onBeforeUnmount(() => {
    if (selectedImagePreview.value) {
        URL.revokeObjectURL(selectedImagePreview.value);
    }
});
</script>

<template>
    <AdminLayout>
        <Head title="Promotional Popup" />
        <div class="d-flex flex-wrap align-items-start justify-content-between gap-3">
            <div><h2 class="mb-1">Promotional Popup</h2><p class="text-muted mb-0">Manage the promotion shown to visitors when they open the website.</p></div>
            <span class="badge rounded-pill px-3 py-2" :class="form.is_active ? 'text-bg-success' : 'text-bg-secondary'">{{ form.is_active ? 'Active' : 'Inactive' }}</span>
        </div>

        <form class="card popup-form mt-4 p-3 p-md-4" @submit.prevent="submit">
            <div class="row g-4">
                <div class="col-12 col-lg-6">
                    <label class="form-label fw-semibold">Promotional image</label>
                    <div v-if="selectedImagePreview || (promotionalPopup?.image_url && !form.remove_image)" class="popup-preview mb-3">
                        <img :src="selectedImagePreview || promotionalPopup.image_url" alt="Promotional Popup preview">
                    </div>
                    <div v-else class="popup-placeholder mb-3"><i class="bi bi-image"></i><span>No promotional image</span></div>
                    <input type="file" class="form-control" accept="image/jpeg,image/png,image/webp" @change="selectImage">
                    <small class="d-block text-muted mt-2">JPG, PNG, or WebP; up to 5 MB. Landscape or square images work best.</small>
                    <small class="d-block text-danger">{{ form.errors.image_upload }}</small>
                    <button v-if="selectedImagePreview || (promotionalPopup?.image_url && !form.remove_image)" type="button" class="btn btn-sm btn-outline-danger mt-3" @click="removeImage">Delete image</button>
                    <p v-else-if="form.remove_image" class="small text-muted mt-2 mb-0">The existing image will be deleted when you save.</p>
                </div>

                <div class="col-12 col-lg-6">
                    <div class="mb-3"><label for="popup-title" class="form-label fw-semibold">Title <span class="text-muted fw-normal">(optional)</span></label><input id="popup-title" v-model="form.title" class="form-control" maxlength="255" placeholder="Special Braj Yatra Offer"><small class="text-danger">{{ form.errors.title }}</small></div>
                    <div class="mb-4"><label for="popup-cta" class="form-label fw-semibold">CTA / link URL <span class="text-muted fw-normal">(optional)</span></label><input id="popup-cta" v-model="form.cta_url" type="text" class="form-control" maxlength="2048" placeholder="/packages or https://example.com/packages"><small class="text-danger">{{ form.errors.cta_url }}</small></div>
                    <div class="form-check form-switch mb-4"><input id="popup-active" v-model="form.is_active" class="form-check-input" type="checkbox"><label class="form-check-label fw-semibold" for="popup-active">Show this popup on the website</label></div>
                    <button class="btn btn-svtp" :disabled="form.processing">{{ form.processing ? 'Saving…' : 'Save Promotional Popup' }}</button>
                </div>
            </div>
        </form>
    </AdminLayout>
</template>

<style scoped>
.popup-form { max-width: 980px; border-radius: 1rem; }.popup-preview,.popup-placeholder { display: grid; width: 100%; aspect-ratio: 16 / 10; place-items: center; overflow: hidden; border: 1px solid #475569; border-radius: .8rem; background: #0b1322; }.popup-preview img { width: 100%; height: 100%; object-fit: contain; }.popup-placeholder { gap: .5rem; color: #94a3b8; }.popup-placeholder i { font-size: 2rem; }
</style>
