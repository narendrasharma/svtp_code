<script setup>
import { computed } from 'vue';
import { Link, useForm } from '@inertiajs/vue3';
import { watch } from 'vue';
import AdminLayout from '../../../Layouts/AdminLayout.vue';
import AiAssist from '../../../Components/AiAssist.vue';
import { appUrl } from '../../../appUrl';

const props = defineProps({
    destination: { type: Object, default: null },
    cities: Array,
    countries: { type: Array, default: () => [] },
    states: { type: Array, default: () => [] },
    parents: { type: Array, default: () => [] },
    destinationTypes: { type: Array, default: () => [] },
});
const form = useForm({
    name: props.destination?.name ?? '',
    slug: props.destination?.slug ?? '',
    excerpt: props.destination?.excerpt ?? '',
    country_id: props.destination?.country_id ?? '',
    state_id: props.destination?.state_id ?? '',
    city_id: props.destination?.city_id ?? '',
    parent_id: props.destination?.parent_id ?? '',
    destination_type: props.destination?.destination_type ?? '',
    latitude: props.destination?.latitude ?? '',
    longitude: props.destination?.longitude ?? '',
    description: props.destination?.description ?? '',
    image_upload: null,
    remove_image: false,
    meta_description: props.destination?.meta_description ?? '',
    meta_title: props.destination?.meta_title ?? '',
    // -----------------------------------------------------------------
    // Active flag – default to true for new destinations, keep existing value when editing
    // -----------------------------------------------------------------
    is_active: props.destination?.is_active ?? true,
    is_featured: props.destination?.is_featured ?? false,
    sort_order: props.destination?.sort_order ?? 0,
});

// Dependent selects: Country → State → City (shared geography).
const visibleStates = computed(() => {
    if (!form.country_id) return props.states ?? [];
    return (props.states ?? []).filter(s => !s.country_id || String(s.country_id) === String(form.country_id));
});
const visibleCities = computed(() => {
    return (props.cities ?? []).filter(c => {
        if (form.city_id && String(c.id) === String(form.city_id)) return true;
        if (form.country_id && c.country_id && String(c.country_id) !== String(form.country_id)) return false;
        if (form.state_id && c.state_id && String(c.state_id) !== String(form.state_id)) return false;
        return true;
    });
});

watch(() => form.name, (name) => {
    if (!props.destination) form.slug = name.toLowerCase().trim().replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '');
});

function submit() {
    const url = props.destination
        ? `${appUrl('/admin/destinations')}/${props.destination.id}`
        : appUrl('/admin/destinations');
    const payload = (data) => {
        const out = { ...data };
        ['country_id', 'state_id', 'city_id', 'parent_id'].forEach(k => { if (out[k] === '') out[k] = null; });
        if (out.destination_type === '') out.destination_type = null;
        if (out.latitude === '') out.latitude = null;
        if (out.longitude === '') out.longitude = null;
        return out;
    };
    if (props.destination) {
        form.transform((data) => ({ ...payload(data), _method: 'put' })).post(url, { forceFormData: true });
    } else {
        form.transform(payload).post(url, { forceFormData: true });
    }
}

function applySeoDraft(draft) {
    form.meta_title = draft.title;
    form.meta_description = draft.description;
}

function selectImage(event) {
    form.image_upload = event.target.files?.[0] ?? null;

    if (form.image_upload) {
        form.remove_image = false;
    }
}
</script>

<template>
    <AdminLayout>
        <h2>{{ destination ? 'Edit' : 'New' }} Destination</h2>
        <form class="mt-3" style="max-width: 640px;" @submit.prevent="submit">
            <label class="form-label">Name</label>
            <input v-model="form.name" class="form-control" required>
            <small class="text-danger">{{ form.errors.name }}</small>

            <label class="form-label mt-3">Slug</label>
            <input v-model="form.slug" class="form-control" required>
            <small class="text-danger">{{ form.errors.slug }}</small>

            <label class="form-label mt-3">Country (optional)</label>
            <select v-model="form.country_id" class="form-select">
                <option value="">No country</option>
                <option v-for="country in countries" :key="country.id" :value="country.id">{{ country.name }}</option>
            </select>
            <small class="text-danger">{{ form.errors.country_id }}</small>

            <label class="form-label mt-3">State / region (optional)</label>
            <select v-model="form.state_id" class="form-select">
                <option value="">No state</option>
                <option v-for="state in visibleStates" :key="state.id" :value="state.id">{{ state.name }}</option>
            </select>
            <small class="text-danger">{{ form.errors.state_id }}</small>

            <label class="form-label mt-3">City (optional)</label>
            <select v-model="form.city_id" class="form-select">
                <option value="">No city</option>
                <option v-for="city in visibleCities" :key="city.id" :value="city.id">{{ city.name }}</option>
            </select>
            <small class="text-danger">{{ form.errors.city_id }}</small>

            <label class="form-label mt-3">Parent destination (optional)</label>
            <select v-model="form.parent_id" class="form-select">
                <option value="">No parent</option>
                <option v-for="parent in parents" :key="parent.id" :value="parent.id">{{ parent.name }}</option>
            </select>
            <small class="text-danger">{{ form.errors.parent_id }}</small>

            <label class="form-label mt-3">Destination type (optional)</label>
            <select v-model="form.destination_type" class="form-select">
                <option value="">Unspecified</option>
                <option v-for="type in destinationTypes" :key="type" :value="type">{{ type }}</option>
            </select>
            <small class="text-danger">{{ form.errors.destination_type }}</small>

            <div class="row">
                <div class="col-md-6">
                    <label class="form-label mt-3">Latitude</label>
                    <input v-model="form.latitude" type="number" step="0.0000001" min="-90" max="90" class="form-control">
                    <small class="text-danger">{{ form.errors.latitude }}</small>
                </div>
                <div class="col-md-6">
                    <label class="form-label mt-3">Longitude</label>
                    <input v-model="form.longitude" type="number" step="0.0000001" min="-180" max="180" class="form-control">
                    <small class="text-danger">{{ form.errors.longitude }}</small>
                </div>
            </div>

            <label class="form-label mt-3" for="destination-excerpt">Excerpt / Short Description</label>
            <textarea id="destination-excerpt" v-model="form.excerpt" class="form-control" rows="2" maxlength="500" placeholder="Short summary for cards and page hero areas."></textarea>
            <small class="d-block text-muted">Short summary used in cards and page hero areas. Keep it concise; the full description appears below.</small>
            <small class="text-danger">{{ form.errors.excerpt }}</small>

            <label class="form-label mt-3">Description</label>
            <textarea v-model="form.description" class="form-control" rows="5" placeholder="Introduce this destination to travellers."></textarea>
            <AiAssist content-type="destination" field="description" output-format="plain" :source="form.description"
                :context="{ name: form.name, excerpt: form.excerpt }" :entity-id="destination?.id"
                :endpoint="appUrl('/admin/ai/content')" @apply="form.description = $event" />
            <small class="text-danger">{{ form.errors.description }}</small>

            <div class="mt-3">
                <label class="form-label">Image</label>
                <div v-if="destination?.image && !form.remove_image" class="mb-2">
                    <img :src="destination.image" :alt="`${destination.name} current image`" class="rounded border" style="width: 180px; height: 110px; object-fit: cover;">
                    <div>
                        <button type="button" class="btn btn-sm btn-outline-danger mt-2" @click="form.remove_image = true">Remove image</button>
                    </div>
                </div>
                <p v-else-if="destination?.image && form.remove_image" class="small text-muted mb-2">The current image will be removed when you save.</p>
                <input type="file" class="form-control" accept="image/jpeg,image/png,image/webp" @change="selectImage">
                <small class="d-block text-muted mt-1">JPG, PNG, or WebP; up to 5 MB.</small>
                <small class="text-danger">{{ form.errors.image_upload }}</small>
            </div>

            <!-- -----------------------------------------------------------------
                 Active / Inactive toggle – uses the same Bootstrap switch style
                 ----------------------------------------------------------------- -->
            <div class="form-check form-switch mt-3">
                <input class="form-check-input" type="checkbox" id="isActiveSwitch" v-model="form.is_active">
                <label class="form-check-label" for="isActiveSwitch">
                    {{ form.is_active ? 'Active' : 'Inactive' }}
                </label>
                <small class="text-danger">{{ form.errors.is_active }}</small>
            </div>

            <div class="form-check form-switch mt-2">
                <input class="form-check-input" type="checkbox" id="isFeaturedSwitch" v-model="form.is_featured">
                <label class="form-check-label" for="isFeaturedSwitch">
                    {{ form.is_featured ? 'Featured' : 'Not featured' }}
                </label>
                <small class="text-danger">{{ form.errors.is_featured }}</small>
            </div>

            <label class="form-label mt-3">Sort order</label>
            <input v-model="form.sort_order" type="number" min="0" class="form-control">
            <small class="text-danger">{{ form.errors.sort_order }}</small>

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
                                Leave blank to use the destination name automatically.
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
                            placeholder="Describe this destination for search engines"
                        ></textarea>

                        <div class="d-flex justify-content-between mt-1">
                            <small class="text-muted">
                                Leave blank to generate from the destination description.
                            </small>

                            <small class="text-muted">
                                {{ form.meta_description.length }}/170
                            </small>
                        </div>

                        <small class="text-danger">
                            {{ form.errors.meta_description }}
                        </small>
                    </div>
                    <AiAssist content-type="destination" field="seo" mode="seo" :source="form.description"
                        :context="{ name: form.name, excerpt: form.excerpt }" :entity-id="destination?.id"
                        :endpoint="appUrl('/admin/ai/content')" @apply="applySeoDraft" />

                </div>
            </div>


            <div class="d-flex gap-2 mt-4">
                <button class="btn btn-svtp" :disabled="form.processing">Save Destination</button>
                <Link :href="appUrl('/admin/destinations')" class="btn btn-outline-secondary">Cancel</Link>
            </div>
        </form>
    </AdminLayout>
</template>
