<script setup>
import { appUrl } from '../../../appUrl';
import AdminLayout from '../../../Layouts/AdminLayout.vue';
import { useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import axios from 'axios';

const props = defineProps({ package: Object, cities: Array });

const form = useForm({
    title: props.package?.title ?? '',
    city_id: props.package?.city_id ?? '',
    duration_days: props.package?.duration_days ?? 1,
    duration_nights: props.package?.duration_nights ?? 0,
    price: props.package?.price ?? '',
    discounted_price: props.package?.discounted_price ?? '',
    overview: props.package?.overview ?? '',
    is_featured: props.package?.is_featured ?? false,
    is_active: props.package?.is_active ?? true,
});

const aiNotes = ref('');
const aiLoading = ref(false);

async function generateWithAi() {
    aiLoading.value = true;
    try {
        const { data } = await axios.post(appUrl('/admin/packages/ai-draft-itinerary'), { notes: aiNotes.value });
        form.overview = data.draft; // admin reviews/edits before saving
    } finally {
        aiLoading.value = false;
    }
}

function submit() {
    if (props.package) {
        form.put(`${appUrl('/admin/packages')}/${props.package.id}`);
    } else {
        form.post(appUrl('/admin/packages'));
    }
}
</script>

<template>
    <AdminLayout>
        <h2>{{ package ? 'Edit' : 'New' }} Package</h2>
        <form @submit.prevent="submit" class="mt-3" style="max-width: 640px;">
            <input v-model="form.title" class="form-control mb-2" placeholder="Title" required />
            <select v-model="form.city_id" class="form-select mb-2" required>
                <option value="">Select City</option>
                <option v-for="c in cities" :key="c.id" :value="c.id">{{ c.name }}</option>
            </select>
            <div class="row">
                <div class="col-4"><input v-model.number="form.duration_days" type="number" class="form-control mb-2" placeholder="Days" /></div>
                <div class="col-4"><input v-model.number="form.duration_nights" type="number" class="form-control mb-2" placeholder="Nights" /></div>
            </div>
            <div class="row">
                <div class="col-6"><input v-model.number="form.price" type="number" class="form-control mb-2" placeholder="Price" /></div>
                <div class="col-6"><input v-model.number="form.discounted_price" type="number" class="form-control mb-2" placeholder="Discounted Price" /></div>
            </div>

            <div class="border rounded p-2 mb-2 bg-light">
                <label class="form-label small fw-semibold">AI Itinerary Assistant</label>
                <textarea v-model="aiNotes" class="form-control mb-2" rows="2" placeholder="e.g. Gokul, Mathura, Vrindavan temples list..."></textarea>
                <button type="button" class="btn btn-sm btn-outline-secondary" :disabled="aiLoading" @click="generateWithAi">
                    {{ aiLoading ? 'Generating...' : 'Generate Draft Overview' }}
                </button>
            </div>

            <textarea v-model="form.overview" class="form-control mb-2" rows="4" placeholder="Overview"></textarea>

            <div class="form-check mb-2">
                <input v-model="form.is_featured" type="checkbox" class="form-check-input" id="featured" />
                <label class="form-check-label" for="featured">Featured</label>
            </div>

            <button class="btn btn-svtp" :disabled="form.processing">Save Package</button>
        </form>
    </AdminLayout>
</template>
