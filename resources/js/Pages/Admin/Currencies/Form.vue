<script setup>
import { Link, useForm } from '@inertiajs/vue3';
import AdminLayout from '../../../Layouts/AdminLayout.vue';
import { appUrl } from '../../../appUrl';

const props = defineProps({
    currency: { type: Object, default: null },
});

const isEdit = !!props.currency?.id;

const form = useForm({
    code: props.currency?.code ?? '',
    name: props.currency?.name ?? '',
    symbol: props.currency?.symbol ?? '',
    decimal_digits: props.currency?.decimal_digits ?? 2,
    symbol_position: props.currency?.symbol_position ?? 'before',
    is_active: props.currency?.is_active ?? true,
    is_default_display: props.currency?.is_default_display ?? false,
    sort_order: props.currency?.sort_order ?? 0,
});

function submit() {
    if (isEdit) {
        form.put(appUrl(`/admin/currencies/${props.currency.id}`));
    } else {
        form.post(appUrl('/admin/currencies'));
    }
}
</script>

<template>
    <AdminLayout>
        <div class="text-muted small mb-1">Admin / System / Currencies</div>
        <h2 class="mb-1">{{ isEdit ? 'Edit Currency' : 'New Currency' }}</h2>
        <p class="text-muted">ISO 4217 codes only (<code>USD</code>, <code>INR</code>). Symbols are presentation metadata — never identifiers.</p>

        <form class="card mt-3" @submit.prevent="submit">
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-2">
                        <label class="form-label fw-semibold">Code *</label>
                        <input v-model="form.code" type="text" class="form-control" maxlength="3" placeholder="USD" style="text-transform: uppercase;" />
                        <div v-if="form.errors.code" class="text-danger small mt-1">{{ form.errors.code }}</div>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-semibold">Name *</label>
                        <input v-model="form.name" type="text" class="form-control" maxlength="100" placeholder="US Dollar" />
                        <div v-if="form.errors.name" class="text-danger small mt-1">{{ form.errors.name }}</div>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label fw-semibold">Symbol *</label>
                        <input v-model="form.symbol" type="text" class="form-control" maxlength="12" placeholder="$" />
                        <div v-if="form.errors.symbol" class="text-danger small mt-1">{{ form.errors.symbol }}</div>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label fw-semibold">Decimals</label>
                        <input v-model.number="form.decimal_digits" type="number" min="0" max="4" class="form-control" />
                        <div v-if="form.errors.decimal_digits" class="text-danger small mt-1">{{ form.errors.decimal_digits }}</div>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label fw-semibold">Symbol pos.</label>
                        <select v-model="form.symbol_position" class="form-select">
                            <option value="before">Before</option>
                            <option value="after">After</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label fw-semibold">Order</label>
                        <input v-model.number="form.sort_order" type="number" min="0" max="9999" class="form-control" />
                    </div>
                    <div class="col-md-10 d-flex flex-wrap gap-3 align-items-end pb-1">
                        <div class="form-check form-switch">
                            <input v-model="form.is_active" class="form-check-input" type="checkbox" id="curActive" />
                            <label class="form-check-label" for="curActive">Active</label>
                        </div>
                        <div class="form-check form-switch">
                            <input v-model="form.is_default_display" class="form-check-input" type="checkbox" id="curDefault" />
                            <label class="form-check-label" for="curDefault">Default display</label>
                        </div>
                    </div>
                </div>

                <div v-if="form.errors.is_active" class="text-danger small mt-2">{{ form.errors.is_active }}</div>

                <hr class="my-4" />

                <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-warning" :disabled="form.processing">
                        <span v-if="form.processing">Saving…</span>
                        <span v-else><i class="bi bi-check-lg me-1"></i>{{ isEdit ? 'Update Currency' : 'Create Currency' }}</span>
                    </button>
                    <Link :href="appUrl('/admin/currencies')" class="btn btn-outline-secondary">Cancel</Link>
                </div>
            </div>
        </form>
    </AdminLayout>
</template>
