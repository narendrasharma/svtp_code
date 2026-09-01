<script setup>
import { appUrl } from '../../appUrl';
import AdminLayout from '../../Layouts/AdminLayout.vue';
import { useForm } from '@inertiajs/vue3';

defineProps({ banners: Array });
const form = useForm({ image: null, title: '' });

function submit() {
    form.post(appUrl('/admin/banners'), { forceFormData: true });
}
</script>

<template>
    <AdminLayout>
        <h2>Home Banners</h2>
        <form @submit.prevent="submit" class="mb-4" style="max-width: 480px;">
            <input type="file" class="form-control mb-2" @input="form.image = $event.target.files[0]" />
            <input v-model="form.title" class="form-control mb-2" placeholder="Title" />
            <button class="btn btn-svtp">Upload Banner</button>
        </form>
        <div class="row g-3">
            <div v-for="b in banners" :key="b.id" class="col-md-3">
                <img :src="`${appUrl('/storage')}/${b.image_path}`" class="img-fluid rounded" />
            </div>
        </div>
    </AdminLayout>
</template>
