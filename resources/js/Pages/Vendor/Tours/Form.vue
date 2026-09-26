<script setup>
import { computed, ref } from 'vue';
import { Link, router, useForm } from '@inertiajs/vue3';
import VendorLayout from '../../../Layouts/VendorLayout.vue';
import { appUrl } from '../../../appUrl';
import AiAssist from '../../../Components/AiAssist.vue';

const props = defineProps({
    tour: { type: Object, default: null },
    cities: { type: Array, required: true },
    categories: { type: Array, required: true },
    destinations: { type: Array, required: true },
    places: { type: Array, required: true },
    tags: { type: Array, required: true },
});

const isEdit = !!props.tour;

const form = useForm({
    title: props.tour?.title ?? '',
    city_id: props.tour?.city_id ?? '',
    duration_days: props.tour?.duration_days ?? 3,
    duration_nights: props.tour?.duration_nights ?? 2,
    price: props.tour?.price ?? '',
    discounted_price: props.tour?.discounted_price ?? '',
    overview: props.tour?.overview ?? '',
    day_wise_itinerary: Array.isArray(props.tour?.day_wise_itinerary)
        ? props.tour.day_wise_itinerary.map((day) => ({
            day: day.day,
            title: day.title ?? '',
            points: Array.isArray(day.points) ? [...day.points] : [],
        })) : [],
    inclusions: props.tour?.inclusions ?? [],
    exclusions: props.tour?.exclusions ?? [],
    gallery: props.tour?.gallery ?? [],
    cover_image_upload: null,
    gallery_uploads: [],
    category_id: props.tour?.category_id ?? '',
    destination_ids: props.tour?.destinations?.map(d => d.id) ?? props.tour?.destination_ids ?? [],
    place_ids: props.tour?.places?.map(p => p.id) ?? props.tour?.place_ids ?? [],
    tag_ids: props.tour?.tags?.map(t => t.id) ?? props.tour?.tag_ids ?? [],
    meta_title: props.tour?.meta_title ?? '',
    meta_description: props.tour?.meta_description ?? '',
    is_active: props.tour?.is_active ?? false,
});

const coverPreview = ref(props.tour?.cover_image || null);
const galleryFiles = ref([]);
const tourAiContext = computed(() => ({
    title: form.title,
    duration_days: Number(form.duration_days) || undefined,
    category: props.categories.find((item) => Number(item.id) === Number(form.category_id))?.name,
    destination: props.destinations
        .filter((item) => form.destination_ids.map(Number).includes(Number(item.id)))
        .map((item) => item.name).join(', ').slice(0, 150),
    places: props.places
        .filter((item) => form.place_ids.map(Number).includes(Number(item.id)))
        .map((item) => item.name).slice(0, 15),
}));

function applySeoDraft(draft) {
    form.meta_title = draft.title;
    form.meta_description = draft.description;
}

function updatePoints(day, event) {
    day.points = event.target.value.split(/\n|,/).map((point) => point.trim()).filter(Boolean);
}

function onCoverChange(e) {
    const file = e.target.files[0];
    form.cover_image_upload = file;
    if (file) coverPreview.value = URL.createObjectURL(file);
}
function onGalleryChange(e) {
    const files = Array.from(e.target.files || []);
    form.gallery_uploads = files;
    galleryFiles.value = files.map(f => URL.createObjectURL(f));
}
function removeGalleryItem(index) {
    form.gallery.splice(index, 1);
}
function addItineraryDay() {
    form.day_wise_itinerary.push({ day: form.day_wise_itinerary.length + 1, title: '', points: [''] });
}
function removeItineraryDay(index) {
    form.day_wise_itinerary.splice(index, 1);
}
function submit() {
    if (isEdit) {
        router.post(appUrl(`/vendor/tours/${props.tour.id}`) + '?_method=PUT', form.data(), { forceFormData: true });
    } else {
        form.post(appUrl('/vendor/tours'), { forceFormData: true });
    }
}
</script>

<template>
    <VendorLayout>
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h2 class="mb-0">{{ isEdit ? 'Edit Tour' : 'Create Tour' }}</h2>
            <Link :href="appUrl('/vendor/tours')" class="btn btn-outline-secondary btn-sm">Back to Tours</Link>
        </div>

        <div v-if="tour && tour.moderation_status" class="alert" :class="{
            'alert-warning': tour.moderation_status === 'pending_review',
            'alert-success': tour.moderation_status === 'approved',
            'alert-info': tour.moderation_status === 'changes_requested',
            'alert-danger': tour.moderation_status === 'rejected',
            'alert-secondary': tour.moderation_status === 'draft'
        }">
            <strong>Moderation:</strong> {{ tour.moderation_status }}
            <span v-if="tour.review_note"> — {{ tour.review_note }}</span>
            <span v-if="tour.moderation_status === 'approved'" class="small d-block">Editing will reset to draft and hide from public until re-approved.</span>
        </div>

        <form @submit.prevent="submit" enctype="multipart/form-data" class="card p-4">
            <div class="row g-3">
                <div class="col-12">
                    <label class="form-label">Title *</label>
                    <input v-model="form.title" type="text" class="form-control" required />
                    <div v-if="form.errors.title" class="text-danger small">{{ form.errors.title }}</div>
                </div>

                <div class="col-md-4">
                    <label class="form-label">City *</label>
                    <select v-model="form.city_id" class="form-select" required>
                        <option value="">Select city</option>
                        <option v-for="c in cities" :key="c.id" :value="c.id">{{ c.name }}</option>
                    </select>
                    <div v-if="form.errors.city_id" class="text-danger small">{{ form.errors.city_id }}</div>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Days *</label>
                    <input v-model.number="form.duration_days" type="number" min="1" class="form-control" required />
                </div>
                <div class="col-md-2">
                    <label class="form-label">Nights</label>
                    <input v-model.number="form.duration_nights" type="number" min="0" class="form-control" />
                </div>
                <div class="col-md-2">
                    <label class="form-label">Price *</label>
                    <input v-model.number="form.price" type="number" min="0" step="0.01" class="form-control" required />
                </div>
                <div class="col-md-2">
                    <label class="form-label">Discounted</label>
                    <input v-model.number="form.discounted_price" type="number" min="0" step="0.01" class="form-control" />
                </div>

                <div class="col-12">
                    <label class="form-label">Overview</label>
                    <textarea v-model="form.overview" rows="4" class="form-control"></textarea>
                    <AiAssist content-type="tour" field="overview" output-format="plain" :source="form.overview"
                        :context="tourAiContext" :entity-id="tour?.id" :endpoint="appUrl('/vendor/ai/content')"
                        @apply="form.overview = $event" />
                </div>

                <div class="col-md-6">
                    <label class="form-label">Category</label>
                    <select v-model="form.category_id" class="form-select">
                        <option value="">None</option>
                        <option v-for="cat in categories" :key="cat.id" :value="cat.id">{{ cat.name }}</option>
                    </select>
                </div>

                <div class="col-md-6">
                    <label class="form-label">Destinations</label>
                    <select multiple v-model="form.destination_ids" class="form-select" size="3">
                        <option v-for="d in destinations" :key="d.id" :value="d.id">{{ d.name }}</option>
                    </select>
                </div>

                <div class="col-md-6">
                    <label class="form-label">Places</label>
                    <select multiple v-model="form.place_ids" class="form-select" size="3">
                        <option v-for="p in places" :key="p.id" :value="p.id">{{ p.name }}</option>
                    </select>
                </div>

                <div class="col-md-6">
                    <label class="form-label">Tags</label>
                    <select multiple v-model="form.tag_ids" class="form-select" size="3">
                        <option v-for="t in tags" :key="t.id" :value="t.id">{{ t.name }}</option>
                    </select>
                </div>

                <div class="col-12">
                    <label class="form-label">Cover Image</label>
                    <input type="file" accept="image/*" class="form-control" @change="onCoverChange" />
                    <img v-if="coverPreview" :src="coverPreview" alt="cover" style="max-width: 200px; margin-top: 8px; border-radius: 8px;" />
                    <div v-if="tour?.cover_image && !coverPreview" class="small text-muted mt-1">Current: <a :href="tour.cover_image" target="_blank">view</a></div>
                </div>

                <div class="col-12">
                    <label class="form-label">Gallery Images</label>
                    <input type="file" multiple accept="image/*" class="form-control" @change="onGalleryChange" />
                    <div v-if="form.gallery.length" class="d-flex flex-wrap gap-2 mt-2">
                        <div v-for="(img, idx) in form.gallery" :key="idx" class="position-relative">
                            <img :src="img" alt="" style="width: 80px; height: 80px; object-fit: cover; border-radius: 8px;" />
                            <button type="button" class="btn btn-sm btn-danger position-absolute top-0 end-0" style="padding: 0 4px; font-size: 10px;" @click="removeGalleryItem(idx)">×</button>
                        </div>
                    </div>
                </div>

                <div class="col-12">
                    <label class="form-label">Itinerary</label>
                    <AiAssist content-type="tour" field="itinerary" mode="itinerary" :source="form.day_wise_itinerary"
                        :context="tourAiContext" :entity-id="tour?.id" :endpoint="appUrl('/vendor/ai/content')"
                        @apply="form.day_wise_itinerary = $event.days" />
                    <div v-for="(day, idx) in form.day_wise_itinerary" :key="idx" class="border rounded p-3 mb-2">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <strong>Day {{ day.day }}</strong>
                            <button type="button" class="btn btn-sm btn-outline-danger" @click="removeItineraryDay(idx)">Remove</button>
                        </div>
                        <input v-model="day.title" placeholder="Title" class="form-control form-control-sm mb-2" />
                        <textarea :value="day.points.join('\n')" @change="updatePoints(day, $event)" placeholder="One point per line" class="form-control form-control-sm"></textarea>
                    </div>
                    <button type="button" class="btn btn-sm btn-outline-secondary" @click="addItineraryDay">+ Add Day</button>
                </div>

                <div class="col-md-6">
                    <label class="form-label">Meta Title</label>
                    <input v-model="form.meta_title" type="text" class="form-control" maxlength="70" />
                </div>
                <div class="col-md-6">
                    <label class="form-label">Meta Description</label>
                    <input v-model="form.meta_description" type="text" class="form-control" maxlength="170" />
                </div>
                <div class="col-12">
                    <AiAssist content-type="tour" field="seo" mode="seo" :source="form.overview"
                        :context="tourAiContext" :entity-id="tour?.id" :endpoint="appUrl('/vendor/ai/content')"
                        @apply="applySeoDraft" />
                </div>
            </div>

            <div class="d-flex gap-2 mt-4">
                <button type="submit" class="btn btn-svtp" :disabled="form.processing">{{ isEdit ? 'Update Draft' : 'Create Draft' }}</button>
                <Link :href="appUrl('/vendor/tours')" class="btn btn-outline-secondary">Cancel</Link>
            </div>
            <p class="small text-muted mt-2">Drafts are private. Submit for review from the tours list when ready.</p>
        </form>
    </VendorLayout>
</template>
