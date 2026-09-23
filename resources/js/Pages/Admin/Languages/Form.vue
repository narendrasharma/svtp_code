<script setup>
import { Link, useForm } from '@inertiajs/vue3';
import AdminLayout from '../../../Layouts/AdminLayout.vue';
import { appUrl } from '../../../appUrl';

const props = defineProps({
    language: { type: Object, default: null },
});

const isEdit = !!props.language?.id;

const form = useForm({
    code: props.language?.code ?? '',
    locale: props.language?.locale ?? '',
    name: props.language?.name ?? '',
    native_name: props.language?.native_name ?? '',
    is_active: props.language?.is_active ?? true,
    is_default: props.language?.is_default ?? false,
    is_rtl: props.language?.is_rtl ?? false,
    sort_order: props.language?.sort_order ?? 0,
    date_format: props.language?.date_format ?? '',
});

function submit() {
    if (isEdit) {
        form.put(appUrl(`/admin/languages/${props.language.id}`));
    } else {
        form.post(appUrl('/admin/languages'));
    }
}
</script>

<template>
    <AdminLayout>
        <div class="text-muted small mb-1">Admin / System / Languages</div>
        <h2 class="mb-1">{{ isEdit ? 'Edit Language' : 'New Language' }}</h2>
        <p class="text-muted">Codes use BCP-47 style (<code>en</code>, <code>hi</code>, <code>ar</code>). Flags are not required.</p>

        <form class="card mt-3" @submit.prevent="submit">
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-3">
                        <label class="form-label fw-semibold">Code *</label>
                        <input v-model="form.code" type="text" class="form-control" maxlength="12" placeholder="en" />
                        <div v-if="form.errors.code" class="text-danger small mt-1">{{ form.errors.code }}</div>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-semibold">Locale *</label>
                        <input v-model="form.locale" type="text" class="form-control" maxlength="12" placeholder="en" />
                        <div v-if="form.errors.locale" class="text-danger small mt-1">{{ form.errors.locale }}</div>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-semibold">Name *</label>
                        <input v-model="form.name" type="text" class="form-control" maxlength="100" placeholder="English" />
                        <div v-if="form.errors.name" class="text-danger small mt-1">{{ form.errors.name }}</div>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-semibold">Native name *</label>
                        <input v-model="form.native_name" type="text" class="form-control" maxlength="100" placeholder="English" />
                        <div v-if="form.errors.native_name" class="text-danger small mt-1">{{ form.errors.native_name }}</div>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-semibold">Order</label>
                        <input v-model.number="form.sort_order" type="number" min="0" max="9999" class="form-control" />
                        <div v-if="form.errors.sort_order" class="text-danger small mt-1">{{ form.errors.sort_order }}</div>
                    </div>
                    <div class="col-md-5">
                        <label class="form-label fw-semibold">Date format <span class="text-muted fw-normal">(optional)</span></label>
                        <input v-model="form.date_format" type="text" class="form-control" maxlength="50" placeholder="Intl default" />
                    </div>
                    <div class="col-md-4 d-flex flex-wrap gap-3 align-items-end pb-1">
                        <div class="form-check form-switch">
                            <input v-model="form.is_active" class="form-check-input" type="checkbox" id="langActive" />
                            <label class="form-check-label" for="langActive">Active</label>
                        </div>
                        <div class="form-check form-switch">
                            <input v-model="form.is_default" class="form-check-input" type="checkbox" id="langDefault" />
                            <label class="form-check-label" for="langDefault">Default</label>
                        </div>
                        <div class="form-check form-switch">
                            <input v-model="form.is_rtl" class="form-check-input" type="checkbox" id="langRtl" />
                            <label class="form-check-label" for="langRtl">RTL</label>
                        </div>
                    </div>
                </div>

                <div v-if="form.errors.is_active" class="text-danger small mt-2">{{ form.errors.is_active }}</div>

                <hr class="my-4" />

                <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-warning" :disabled="form.processing">
                        <span v-if="form.processing">Saving…</span>
                        <span v-else><i class="bi bi-check-lg me-1"></i>{{ isEdit ? 'Update Language' : 'Create Language' }}</span>
                    </button>
                    <Link :href="appUrl('/admin/languages')" class="btn btn-outline-secondary">Cancel</Link>
                </div>
            </div>
        </form>
    </AdminLayout>
</template>
