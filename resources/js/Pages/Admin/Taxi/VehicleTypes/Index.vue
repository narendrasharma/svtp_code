<script setup>
import { ref } from 'vue';
import { Link, router, useForm } from '@inertiajs/vue3';
import AdminLayout from '../../../../Layouts/AdminLayout.vue';
import Pagination from '../../../../Components/Pagination.vue';
import { appUrl } from '../../../../appUrl';

const props = defineProps({
    types: { type: Object, required: true },
});

const createForm = useForm({
    name: '',
    slug: '',
    description: '',
    passenger_capacity: 4,
    luggage_capacity: '',
    is_active: true,
    sort_order: 0,
    image_upload: null,
    remove_image: false,
});

const editForm = useForm({
    id: null,
    name: '',
    description: '',
    passenger_capacity: 4,
    luggage_capacity: '',
    is_active: true,
    sort_order: 0,
    image_path: null,
    image_upload: null,
    remove_image: false,
});

const editingId = ref(null);
const createImagePreview = ref(null);
const editImagePreview = ref(null);

function store() {
    createForm.post(appUrl('/admin/taxi/vehicle-types'), {
        preserveScroll: true,
        forceFormData: true,
        onSuccess: () => {
            createForm.reset('name', 'slug', 'description', 'passenger_capacity', 'luggage_capacity', 'sort_order', 'image_upload', 'remove_image');
            clearPreview(createImagePreview);
        },
    });
}

function startEdit(type) {
    editingId.value = type.id;
    editForm.reset();
    editForm.id = type.id;
    editForm.name = type.name;
    editForm.description = type.description ?? '';
    editForm.passenger_capacity = type.passenger_capacity;
    editForm.luggage_capacity = type.luggage_capacity;
    editForm.is_active = Boolean(type.is_active);
    editForm.sort_order = type.sort_order ?? 0;
    editForm.image_path = type.image_path ?? null;
    editForm.image_upload = null;
    editForm.remove_image = false;
    clearPreview(editImagePreview);
}

function cancelEdit() {
    editingId.value = null;
    editForm.reset();
    clearPreview(editImagePreview);
}

function update() {
    if (!editingId.value) return;
    editForm.put(appUrl(`/admin/taxi/vehicle-types/${editingId.value}`), {
        preserveScroll: true,
        forceFormData: true,
        onSuccess: () => {
            cancelEdit();
        },
    });
}

function remove(id) {
    if (!window.confirm('Delete this vehicle type? This cannot be undone.')) return;
    router.delete(appUrl(`/admin/taxi/vehicle-types/${id}`), { preserveScroll: true });
}

function selectImage(form, event, preview) {
    clearPreview(preview);
    form.image_upload = event.target.files?.[0] ?? null;
    form.remove_image = false;

    if (form.image_upload) {
        preview.value = URL.createObjectURL(form.image_upload);
    }
}

function removeImage(form, preview) {
    clearPreview(preview);
    form.image_upload = null;
    form.remove_image = true;
}

function clearPreview(preview) {
    if (preview.value) {
        URL.revokeObjectURL(preview.value);
        preview.value = null;
    }
}
</script>

<template>
    <AdminLayout>
        <div class="d-flex align-items-center justify-content-between flex-wrap gap-3 mb-4">
            <div>
                <h2 class="mt-2 mb-1">Taxi vehicle types</h2>
                <p class="text-muted mb-0">Manage the fleet categories vendors can select.</p>
            </div>
            <Link :href="appUrl('/admin/taxi/vehicles')" class="btn btn-outline-light">Manage vehicles</Link>
        </div>

        <div class="row g-3">
            <div class="col-12 col-xl-4">
                <form class="card" @submit.prevent="store">
                    <div class="card-body">
                        <h5 class="card-title mb-3">Add vehicle type</h5>
                        <div class="mb-3">
                            <label class="form-label small">Name</label>
                            <input v-model="createForm.name" type="text" class="form-control" maxlength="80" required />
                        </div>
                        <div class="mb-3">
                            <label class="form-label small">Slug (optional)</label>
                            <input v-model="createForm.slug" type="text" class="form-control" maxlength="80" placeholder="e.g. sedan" />
                        </div>
                        <div class="mb-3">
                            <label class="form-label small">Description</label>
                            <textarea v-model="createForm.description" class="form-control" rows="2" maxlength="500"></textarea>
                        </div>
                        <div class="mb-3">
                            <label class="form-label small">Representative image</label>
                            <div v-if="createImagePreview" class="d-flex align-items-start gap-2 mb-2">
                                <img :src="createImagePreview" alt="Selected vehicle type preview" class="vehicle-type-image-preview" />
                                <button type="button" class="btn btn-sm btn-outline-danger" @click="removeImage(createForm, createImagePreview)">Remove</button>
                            </div>
                            <input type="file" class="form-control" accept="image/jpeg,image/png,image/webp" @change="selectImage(createForm, $event, createImagePreview)" />
                            <small class="d-block text-muted mt-1">Shown to customers when selecting this vehicle type. Use a clean landscape photo without promotional text. JPG, PNG, or WebP; up to 5 MB.</small>
                            <small class="text-danger">{{ createForm.errors.image_upload }}</small>
                        </div>
                        <div class="row g-2 mb-3">
                            <div class="col-md-6">
                                <label class="form-label small">Passenger capacity</label>
                                <input v-model.number="createForm.passenger_capacity" type="number" min="1" max="60" class="form-control" />
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small">Luggage capacity</label>
                                <input v-model.number="createForm.luggage_capacity" type="number" min="0" max="60" class="form-control" />
                            </div>
                        </div>
                        <div class="row g-2 mb-3">
                            <div class="col-md-6 d-flex align-items-center gap-2">
                                <input id="create-active" v-model="createForm.is_active" type="checkbox" class="form-check-input" />
                                <label for="create-active" class="form-check-label small">Active</label>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small">Sort order</label>
                                <input v-model.number="createForm.sort_order" type="number" min="0" class="form-control" />
                            </div>
                        </div>
                    </div>
                    <div class="card-footer">
                        <button class="btn btn-svtp w-100" :disabled="createForm.processing">Create type</button>
                    </div>
                </form>
            </div>

            <div class="col-12 col-xl-8">
                <div class="card">
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table align-middle mb-0">
                                <thead><tr><th>Name</th><th>Capacity</th><th>Status</th><th>Sort</th><th class="text-end">Actions</th></tr></thead>
                                <tbody>
                                    <tr v-for="type in props.types.data" :key="type.id">
                                        <td>
                                            <div class="fw-semibold">{{ type.name }}</div>
                                            <div class="small text-muted">{{ type.slug }}</div>
                                        </td>
                                        <td>{{ type.passenger_capacity }} pax · {{ type.luggage_capacity ?? 0 }} bags</td>
                                        <td>
                                            <span class="badge" :class="type.is_active ? 'bg-success' : 'bg-secondary'">{{ type.is_active ? 'Active' : 'Inactive' }}</span>
                                        </td>
                                        <td>{{ type.sort_order }}</td>
                                        <td class="text-end">
                                            <div class="btn-group btn-group-sm">
                                                <button class="btn btn-outline-light" @click="startEdit(type)">Edit</button>
                                                <button class="btn btn-outline-danger" @click="remove(type.id)"><i class="bi bi-trash"></i></button>
                                            </div>
                                        </td>
                                    </tr>
                                    <tr v-if="!props.types.data.length"><td colspan="5" class="text-center text-muted py-4">No vehicle types configured yet.</td></tr>
                                </tbody>
                            </table>
                        </div>
                        <Pagination :links="props.types.links" class="p-3" />
                    </div>
                </div>

                <form v-if="editingId" class="card mt-3" @submit.prevent="update">
                    <div class="card-body">
                        <h5 class="card-title mb-3">Edit vehicle type</h5>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label small">Name</label>
                                <input v-model="editForm.name" type="text" class="form-control" required />
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small">Sort order</label>
                                <input v-model.number="editForm.sort_order" type="number" min="0" class="form-control" />
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small">Passenger capacity</label>
                                <input v-model.number="editForm.passenger_capacity" type="number" min="1" max="60" class="form-control" />
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small">Luggage capacity</label>
                                <input v-model.number="editForm.luggage_capacity" type="number" min="0" max="60" class="form-control" />
                            </div>
                            <div class="col-12">
                                <label class="form-label small">Description</label>
                                <textarea v-model="editForm.description" class="form-control" rows="2" maxlength="500"></textarea>
                            </div>
                            <div class="col-12">
                                <label class="form-label small">Representative image</label>
                                <img v-if="editImagePreview" :src="editImagePreview" alt="Selected vehicle type preview" class="vehicle-type-image-preview mb-2" />
                                <img v-else-if="editForm.image_path && !editForm.remove_image" :src="appUrl(`/storage/${editForm.image_path}`)" alt="Current vehicle type image" class="vehicle-type-image-preview mb-2" />
                                <p v-if="editForm.image_path && editForm.remove_image" class="small text-muted">The current image will be removed when you save.</p>
                                <div class="d-flex gap-2 align-items-center">
                                    <input type="file" class="form-control" accept="image/jpeg,image/png,image/webp" @change="selectImage(editForm, $event, editImagePreview)" />
                                    <button v-if="editImagePreview || (editForm.image_path && !editForm.remove_image)" type="button" class="btn btn-outline-danger text-nowrap" @click="removeImage(editForm, editImagePreview)">Remove</button>
                                </div>
                                <small class="d-block text-muted mt-1">Representative image shown to customers; use a clean landscape photo without promotional text.</small>
                                <small class="text-danger">{{ editForm.errors.image_upload }}</small>
                            </div>
                            <div class="col-12">
                                <div class="form-check">
                                    <input id="edit-active" v-model="editForm.is_active" type="checkbox" class="form-check-input" />
                                    <label for="edit-active" class="form-check-label small">Vehicle type is active</label>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="card-footer d-flex gap-2 justify-content-end">
                        <button type="button" class="btn btn-outline-light" @click="cancelEdit">Cancel</button>
                        <button class="btn btn-svtp" :disabled="editForm.processing">Update type</button>
                    </div>
                </form>
            </div>
        </div>
    </AdminLayout>
</template>

<style scoped>
.card { border-radius: 1rem; border: 1px solid rgba(148, 163, 184, .12); background: #101827; color: #e2e8f0; }
.card-title { color: #f8fafc; }
.form-control, .form-select, textarea { background: rgba(15, 23, 42, .65); border-color: rgba(148, 163, 184, .25); color: #f8fafc; }
.form-control:focus, .form-select:focus, textarea:focus { border-color: #f59e0b; box-shadow: 0 0 0 .2rem rgba(245, 158, 11, .18); }
.table { --bs-table-bg: transparent; color: inherit; }
.btn.btn-outline-light { color: #f8fafc; border-color: rgba(148, 163, 184, .35); }
.vehicle-type-image-preview { display: block; width: 100%; max-width: 260px; aspect-ratio: 3 / 2; object-fit: cover; border-radius: .75rem; border: 1px solid rgba(148, 163, 184, .25); }
</style>
