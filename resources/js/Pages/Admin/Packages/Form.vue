<script setup>
import { appUrl } from '../../../appUrl';
import SmartMultiSelect from '../../../Components/SmartMultiSelect.vue';
import AdminLayout from '../../../Layouts/AdminLayout.vue';
import { useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import axios from 'axios';
import { Ckeditor } from '@ckeditor/ckeditor5-vue';

import {
    ClassicEditor,
    Essentials,
    Paragraph,
    Heading,
    Bold,
    Italic,
    Link,
    List,
    BlockQuote,
    Undo,

    Image,
    ImageToolbar,
    ImageCaption,
    ImageStyle,
    ImageResize,
    ImageUpload,
    SimpleUploadAdapter,
} from 'ckeditor5';

import 'ckeditor5/ckeditor5.css';

const editor = ClassicEditor;

const editorConfig = {
    licenseKey: 'GPL',

    plugins: [
        Essentials,
        Paragraph,
        Heading,
        Bold,
        Italic,
        Link,
        List,
        BlockQuote,
        Undo,

        Image,
        ImageToolbar,
        ImageCaption,
        ImageStyle,
        ImageResize,
        ImageUpload,
        SimpleUploadAdapter,
    ],

    toolbar: [
        'undo',
        'redo',
        '|',
        'heading',
        '|',
        'bold',
        'italic',
        'link',
        '|',
        'bulletedList',
        'numberedList',
        'blockQuote',
        '|',
        'imageUpload',

    ],
    simpleUpload: {
        uploadUrl: appUrl('/admin/editor/upload-image'),

        headers: {
            'X-CSRF-TOKEN': document
                .querySelector('meta[name="csrf-token"]')
                ?.getAttribute('content'),
        },
    },

    image: {
        upload: {
            types: ['jpeg', 'png', 'webp'],
        },

        toolbar: [
            'imageTextAlternative',
            'toggleImageCaption',
            '|',
            'imageStyle:inline',
            'imageStyle:block',
            'imageStyle:side',
        ],
    },
};




const props = defineProps({
    package: { type: Object, default: null },
    cities: { type: Array, default: () => [] },
    categories: { type: Array, default: () => [] },
    destinations: { type: Array, default: () => [] },
    places: { type: Array, default: () => [] },
    tags: { type: Array, default: () => [] },
});

const form = useForm({
    meta_title: props.package?.meta_title ?? '',
    meta_description: props.package?.meta_description ?? '',
    title: props.package?.title ?? '',
    city_id: props.package?.city_id ?? '',
    duration_days: props.package?.duration_days ?? 1,
    duration_nights: props.package?.duration_nights ?? 0,
    price: props.package?.price ?? '',
    discounted_price: props.package?.discounted_price ?? '',
    overview: props.package?.overview ?? '',
    category_id: props.package?.category_id ?? '',
    day_wise_itinerary: Array.isArray(props.package?.day_wise_itinerary)
        ? props.package.day_wise_itinerary.map((day, index) => ({
            day: day.day ?? index + 1,
            title: day.title ?? '',
            points: Array.isArray(day.points) && day.points.length ? [...day.points] : [''],
        }))
        : [],
    inclusions: Array.isArray(props.package?.inclusions) ? [...props.package.inclusions] : [],
    exclusions: Array.isArray(props.package?.exclusions) ? [...props.package.exclusions] : [],
    gallery: Array.isArray(props.package?.gallery) ? [...props.package.gallery] : [],
    gallery_uploads: [],
    cover_image_upload: null,
    remove_cover_image: false,
    is_featured: props.package?.is_featured ?? false,
    is_active: props.package?.is_active ?? true,
    destination_ids: props.package?.destinations?.map((destination) => destination.id) ?? [],
    place_ids: props.package?.places?.map((place) => place.id) ?? [],
    tag_ids: props.package?.tags?.map((tag) => tag.id) ?? [],
});

const destinationOptions = computed(() => props.destinations.map((destination) => ({
    id: destination.id,
    label: destination.name,
})));
const placeOptions = computed(() => {
    const destinationIds = form.destination_ids.map(Number);
    const selectedPlaceIds = form.place_ids.map(Number);

    return props.places
        .filter((place) => !destinationIds.length
            || destinationIds.includes(Number(place.destination_id))
            || selectedPlaceIds.includes(Number(place.id)))
        .map((place) => ({
            id: place.id,
            label: place.name,
            meta: place.destination?.name,
        }));
});
const tagOptions = computed(() => props.tags.map((tag) => ({
    id: tag.id,
    label: tag.name,
    meta: tag.is_active ? '' : 'Inactive',
})));

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

function addItineraryDay() {
    form.day_wise_itinerary.push({
        day: form.day_wise_itinerary.length + 1,
        title: '',
        points: [''],
    });
}

function removeItineraryDay(index) {
    form.day_wise_itinerary.splice(index, 1);
}

function addItineraryPoint(day) {
    day.points.push('');
}

function removeItineraryPoint(day, index) {
    day.points.splice(index, 1);
}

function addListItem(field) {
    form[field].push('');
}

function removeListItem(field, index) {
    form[field].splice(index, 1);
}

function selectGalleryUploads(event) {
    form.gallery_uploads = Array.from(event.target.files || []);
}

function selectCoverImage(event) {
    form.cover_image_upload = event.target.files?.[0] ?? null;

    if (form.cover_image_upload) {
        form.remove_cover_image = false;
    }
}

function prepareContentForSubmission() {
    form.day_wise_itinerary = form.day_wise_itinerary
        .map((day, index) => ({
            day: Number(day.day) || index + 1,
            title: day.title.trim(),
            points: day.points.map((point) => point.trim()).filter(Boolean),
        }))
        .filter((day) => day.title || day.points.length);

    ['inclusions', 'exclusions'].forEach((field) => {
        form[field] = form[field].map((item) => item.trim()).filter(Boolean);
    });
}

function submit() {
    prepareContentForSubmission();

    if (props.package) {
        form.transform((data) => ({ ...data, _method: 'put' }))
            .post(`${appUrl('/admin/packages')}/${props.package.id}`, { forceFormData: true });
    } else {
        form.post(appUrl('/admin/packages'), { forceFormData: true });
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
            <select v-model="form.category_id" class="form-select mb-2">
                <option value="">Select Tour Category (optional)</option>
                <option v-for="category in categories" :key="category.id" :value="category.id" :disabled="!category.is_active">
                    {{ category.name }}{{ category.is_active ? '' : ' (Inactive)' }}
                </option>
            </select>
            <small class="d-block text-danger mb-2">{{ form.errors.category_id }}</small>
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

<!--
            <textarea v-model="form.overview" class="form-control mb-2" rows="4" placeholder="Overview"></textarea>
-->
            <div class="mb-3">
                <label class="form-label fw-semibold">Package Overview</label>

                <Ckeditor
                    v-model="form.overview"
                    :editor="editor"
                    :config="editorConfig"
                />

                <small class="text-danger">
                    {{ form.errors.overview }}
                </small>
            </div>


            <div class="border rounded p-3 mb-3">
                <h5 class="mb-3">Package Content</h5>

                <div class="mb-4">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <label class="form-label fw-semibold mb-0">Day-wise Itinerary</label>
                        <button type="button" class="btn btn-sm btn-outline-secondary" @click="addItineraryDay">Add Day</button>
                    </div>
                    <div v-for="(day, dayIndex) in form.day_wise_itinerary" :key="dayIndex" class="border rounded p-2 mb-2 bg-light">
                        <div class="row g-2 align-items-center mb-2">
                            <div class="col-3">
                                <input v-model.number="day.day" type="number" min="1" class="form-control" placeholder="Day" />
                            </div>
                            <div class="col-7">
                                <input v-model="day.title" class="form-control" placeholder="Day title" />
                            </div>
                            <div class="col-2 text-end">
                                <button type="button" class="btn btn-sm btn-outline-danger" aria-label="Remove itinerary day" @click="removeItineraryDay(dayIndex)">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </div>
                        </div>
                        <div v-for="(_, pointIndex) in day.points" :key="pointIndex" class="input-group input-group-sm mb-2">
                            <input v-model="day.points[pointIndex]" class="form-control" placeholder="Itinerary point" />
                            <button type="button" class="btn btn-outline-danger" aria-label="Remove itinerary point" @click="removeItineraryPoint(day, pointIndex)">
                                <i class="bi bi-dash"></i>
                            </button>
                        </div>
                        <button type="button" class="btn btn-sm btn-outline-secondary" @click="addItineraryPoint(day)">Add Point</button>
                    </div>
                    <small class="text-danger">{{ form.errors.day_wise_itinerary }}</small>
                </div>

                <div v-for="field in ['inclusions', 'exclusions']" :key="field" class="mb-3">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <label class="form-label fw-semibold mb-0">{{ field[0].toUpperCase() + field.slice(1) }}</label>
                        <button type="button" class="btn btn-sm btn-outline-secondary" @click="addListItem(field)">Add Item</button>
                    </div>
                    <div v-for="(_, index) in form[field]" :key="index" class="input-group input-group-sm mb-2">
                        <input v-model="form[field][index]" class="form-control" placeholder="Add an item" />
                        <button type="button" class="btn btn-outline-danger" :aria-label="`Remove ${field} item`" @click="removeListItem(field, index)">
                            <i class="bi bi-dash"></i>
                        </button>
                    </div>
                    <small class="text-danger">{{ form.errors[field] }}</small>
                </div>

                <div class="mb-3">
                    <label class="form-label fw-semibold">Cover Image</label>
                    <div v-if="package?.cover_image && !form.remove_cover_image" class="mb-2">
                        <img :src="package.cover_image" :alt="`${package.title} cover image`" class="rounded border" style="width: 220px; height: 125px; object-fit: cover;">
                        <div>
                            <button type="button" class="btn btn-sm btn-outline-danger mt-2" @click="form.remove_cover_image = true">Remove cover image</button>
                        </div>
                    </div>
                    <p v-else-if="package?.cover_image && form.remove_cover_image" class="small text-muted mb-2">The current cover image will be removed when you save.</p>
                    <input type="file" class="form-control" accept="image/jpeg,image/png,image/webp" @change="selectCoverImage" />
                    <small class="text-danger">{{ form.errors.cover_image_upload }}</small>
                </div>

                <div v-if="form.gallery.length" class="mb-3">
                    <label class="form-label fw-semibold">Existing Gallery Images</label>
                    <div class="row g-2">
                        <div v-for="(image, index) in form.gallery" :key="`${image}-${index}`" class="col-6 col-sm-4">
                            <div class="border rounded p-2 h-100">
                                <img :src="image" :alt="`${package?.title ?? 'Package'} gallery image ${index + 1}`" class="rounded w-100" style="height: 100px; object-fit: cover;">
                                <button type="button" class="btn btn-sm btn-outline-danger w-100 mt-2" @click="removeListItem('gallery', index)">Remove</button>
                            </div>
                        </div>
                    </div>
                    <small class="text-danger">{{ form.errors.gallery }}</small>
                </div>

                <div class="mb-3">
                    <label class="form-label fw-semibold">Upload Gallery Images</label>
                    <input type="file" class="form-control" accept="image/jpeg,image/png,image/webp" multiple @change="selectGalleryUploads" />
                    <p class="small text-muted mt-2 mb-1">Recommended: 1600 × 900 px (16:9 landscape). JPG, PNG, or WebP; up to 5 MB each. Images are cropped with their aspect ratio preserved, never stretched.</p>
                    <p v-if="form.gallery_uploads.length" class="small mb-0">{{ form.gallery_uploads.length }} image{{ form.gallery_uploads.length === 1 ? '' : 's' }} selected for upload.</p>
                    <small class="text-danger">{{ form.errors.gallery_uploads }}</small>
                </div>
            </div>

            <div class="border rounded p-3 mb-3">
                <h5 class="mb-3">Tour Classification</h5>

                <label class="form-label fw-semibold">Destinations</label>
                <SmartMultiSelect
                    v-model="form.destination_ids"
                    :options="destinationOptions"
                    placeholder="Search destinations..."
                />
                <small class="text-danger">{{ form.errors.destination_ids }}</small>

                <label class="form-label fw-semibold mt-3">Places / Attractions</label>
                <p class="small text-muted mb-2">Selecting destinations narrows this list while keeping existing place assignments visible.</p>
                <SmartMultiSelect
                    v-model="form.place_ids"
                    :options="placeOptions"
                    placeholder="Search places or destinations..."
                    empty-text="No places are available for the selected destinations."
                />
                <small class="text-danger">{{ form.errors.place_ids }}</small>

                <label class="form-label fw-semibold mt-3">Tags</label>
                <SmartMultiSelect
                    v-model="form.tag_ids"
                    :options="tagOptions"
                    placeholder="Search tags..."
                />
                <small class="text-danger">{{ form.errors.tag_ids }}</small>
            </div>

            <div class="form-check mb-2">
                <input v-model="form.is_featured" type="checkbox" class="form-check-input" id="featured" />
                <label class="form-check-label" for="featured">Featured</label>
            </div>



            <div class="card mt-4">
                <div class="card-header">
                    <strong>
                        <i class="bi bi-search me-2"></i>
                        SEO Settings
                    </strong>
                </div>

                <div class="card-body">

                    <div class="mb-3">
                        <label class="form-label">
                            Meta Title
                        </label>

                        <input
                            v-model="form.meta_title"
                            type="text"
                            class="form-control"
                            maxlength="70"
                            placeholder="Leave blank to generate automatically"
                        >

                        <div class="d-flex justify-content-between mt-1">
                            <small class="text-muted">
                                If empty, the package name will be used automatically.
                            </small>

                            <small class="text-muted">
                                {{ form.meta_title.length }}/70
                            </small>
                        </div>

                        <small class="text-danger">
                            {{ form.errors.meta_title }}
                        </small>
                    </div>


                    <div>
                        <label class="form-label">
                            Meta Description
                        </label>

                        <textarea
                            v-model="form.meta_description"
                            class="form-control"
                            rows="3"
                            maxlength="170"
                            placeholder="Describe this package for search engines"
                        ></textarea>

                        <div class="d-flex justify-content-between mt-1">
                            <small class="text-muted">
                                If empty, the package overview will be used automatically.
                            </small>

                            <small class="text-muted">
                                {{ form.meta_description.length }}/170
                            </small>
                        </div>

                        <small class="text-danger">
                            {{ form.errors.meta_description }}
                        </small>
                    </div>

                </div>
            </div>


            <button class="btn btn-svtp" :disabled="form.processing">Save Package</button>
        </form>
    </AdminLayout>
</template>
