<script setup>
import InputError from '@/Components/InputError.vue';
import Modal from '@/Components/Modal.vue';
import { useForm, usePage } from '@inertiajs/vue3';
import { computed, nextTick, ref } from 'vue';
import { appUrl } from '@/appUrl.js';

const page = usePage();
const isImpersonating = computed(() => !!page.props.impersonation);

const confirmingUserDeletion = ref(false);
const passwordInput = ref(null);

const form = useForm({
    password: '',
});

const confirmUserDeletion = () => {
    confirmingUserDeletion.value = true;

    nextTick(() => passwordInput.value?.focus());
};

const deleteUser = () => {
    form.delete(appUrl('/account/profile'), {
        preserveScroll: true,
        onSuccess: () => closeModal(),
        onError: () => passwordInput.value?.focus(),
        onFinish: () => form.reset(),
    });
};

const closeModal = () => {
    confirmingUserDeletion.value = false;

    form.clearErrors();
    form.reset();
};
</script>

<template>
    <section>
        <header class="mb-3">
            <div class="d-flex align-items-center gap-2 mb-1">
                <span class="d-inline-flex align-items-center justify-content-center rounded-circle flex-shrink-0" style="width: 36px; height: 36px; background: rgba(220,38,38,0.08); border: 1px solid rgba(220,38,38,0.15);"><i class="bi bi-exclamation-triangle-fill" style="color: #DC2626;"></i></span>
                <h3 class="h5 mb-0" style="font-family: var(--font-display); color: #991B1B;">Danger Zone</h3>
            </div>
            <p class="small text-muted mb-0">Permanently delete your account and all associated data. This action cannot be undone.</p>
        </header>

        <div class="rounded-3 p-3 mb-3" style="background: rgba(220,38,38,0.06); border: 1px solid rgba(220,38,38,0.18);">
            <ul class="small mb-0 ps-3" style="color: #7F1D1D;">
                <li>All bookings remain in admin records but your login will be removed</li>
                <li>You will be logged out immediately</li>
                <li>This cannot be undone without support</li>
            </ul>
        </div>

        <div v-if="isImpersonating" class="alert alert-warning d-flex align-items-center gap-2 py-2 small mt-3">
            <i class="bi bi-shield-exclamation"></i>
            Account deletion is disabled while impersonating.
        </div>

        <button
            type="button"
            class="btn btn-danger rounded-pill px-4"
            style="background: #DC2626; border-color: #DC2626; font-weight: 600;"
            :disabled="isImpersonating"
            @click="confirmUserDeletion"
        >
            <i class="bi bi-trash3 me-1"></i> Delete Account
        </button>

        <Modal :show="confirmingUserDeletion" @close="closeModal">
            <div class="p-4 p-sm-5">
                <div class="d-flex align-items-center gap-2 mb-2">
                    <span class="d-inline-flex align-items-center justify-content-center rounded-circle flex-shrink-0" style="width: 40px; height: 40px; background: rgba(220,38,38,0.1);"><i class="bi bi-shield-exclamation" style="color: #DC2626; font-size: 1.25rem;"></i></span>
                    <h2 class="h5 mb-0" style="font-family: var(--font-display); color: #991B1B;">
                        Delete your account?
                    </h2>
                </div>

                <p class="small text-muted">
                    Once deleted, all of your data will be permanently removed. Please enter your password to confirm.
                </p>

                <div class="mt-4">
                    <label for="password" class="form-label small fw-semibold">Password <span class="text-danger">*</span></label>
                    <input
                        id="password"
                        ref="passwordInput"
                        v-model="form.password"
                        type="password"
                        class="form-control"
                        placeholder="Enter your password"
                        @keyup.enter="deleteUser"
                        :class="{ 'is-invalid': form.errors.password }"
                    />
                    <InputError :message="form.errors.password" class="mt-2" />
                </div>

                <div class="mt-4 d-flex justify-content-end gap-2">
                    <button type="button" class="btn btn-outline-secondary rounded-pill px-4" @click="closeModal">
                        Cancel
                    </button>

                    <button
                        type="button"
                        class="btn btn-danger rounded-pill px-4"
                        :class="{ 'opacity-50': form.processing }"
                        :disabled="form.processing"
                        @click="deleteUser"
                    >
                        <span v-if="form.processing" class="spinner-border spinner-border-sm me-2"></span>
                        Permanently Delete
                    </button>
                </div>
            </div>
        </Modal>
    </section>
</template>

<style scoped>
.form-control {
    border: 1.5px solid rgba(43,24,16,0.12);
    border-radius: 12px;
    padding: 0.65rem 0.9rem;
}
.form-control:focus {
    border-color: #DC2626;
    box-shadow: 0 0 0 0.2rem rgba(220,38,38,0.12);
}
</style>
