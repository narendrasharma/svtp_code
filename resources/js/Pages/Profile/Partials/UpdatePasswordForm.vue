<script setup>
import InputError from '@/Components/InputError.vue';
import { useForm, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { appUrl } from '@/appUrl.js';

const page = usePage();
const isImpersonating = computed(() => !!page.props.impersonation);

const passwordInput = ref(null);
const currentPasswordInput = ref(null);

const form = useForm({
    current_password: '',
    password: '',
    password_confirmation: '',
});

const updatePassword = () => {
    form.put(appUrl('/password'), {
        preserveScroll: true,
        onSuccess: () => form.reset(),
        onError: () => {
            if (form.errors.password) {
                form.reset('password', 'password_confirmation');
                passwordInput.value?.focus();
            }
            if (form.errors.current_password) {
                form.reset('current_password');
                currentPasswordInput.value?.focus();
            }
        },
    });
};
</script>

<template>
    <section>
        <header class="mb-4">
            <div class="d-flex align-items-center gap-2 mb-1">
                <span class="d-inline-flex align-items-center justify-content-center rounded-circle flex-shrink-0" style="width: 36px; height: 36px; background: rgba(30,58,138,0.08); border: 1px solid rgba(30,58,138,0.12);"><i class="bi bi-shield-lock-fill" style="color: var(--yamuna);"></i></span>
                <h3 class="h5 mb-0" style="font-family: var(--font-display); color: var(--maroon);">Change Password</h3>
            </div>
            <p class="small text-muted mb-0">Use a strong password you don’t use elsewhere. You’ll need your current password to make this change.</p>
        </header>

        <form @submit.prevent="updatePassword" class="mt-4">
            <div class="row g-3">
                <div class="col-12">
                    <label for="current_password" class="form-label small fw-semibold" style="color: var(--ink);">Current Password <span class="text-danger">*</span></label>
                    <input
                        id="current_password"
                        ref="currentPasswordInput"
                        v-model="form.current_password"
                        type="password"
                        class="form-control"
                        autocomplete="current-password"
                        placeholder="Enter current password"
                        required
                        :class="{ 'is-invalid': form.errors.current_password }"
                    />
                    <InputError :message="form.errors.current_password" class="mt-2" />
                </div>

                <div class="col-12 col-md-6">
                    <label for="password" class="form-label small fw-semibold" style="color: var(--ink);">New Password <span class="text-danger">*</span></label>
                    <input
                        id="password"
                        ref="passwordInput"
                        v-model="form.password"
                        type="password"
                        class="form-control"
                        autocomplete="new-password"
                        placeholder="At least 8 characters"
                        required
                        :class="{ 'is-invalid': form.errors.password }"
                    />
                    <InputError :message="form.errors.password" class="mt-2" />
                </div>

                <div class="col-12 col-md-6">
                    <label for="password_confirmation" class="form-label small fw-semibold" style="color: var(--ink);">Confirm New Password <span class="text-danger">*</span></label>
                    <input
                        id="password_confirmation"
                        v-model="form.password_confirmation"
                        type="password"
                        class="form-control"
                        autocomplete="new-password"
                        placeholder="Repeat new password"
                        required
                        :class="{ 'is-invalid': form.errors.password_confirmation }"
                    />
                    <InputError :message="form.errors.password_confirmation" class="mt-2" />
                </div>
            </div>

            <div v-if="isImpersonating" class="alert alert-warning d-flex align-items-center gap-2 py-2 small mt-3">
                <i class="bi bi-shield-exclamation"></i>
                Password change is disabled while impersonating.
            </div>

            <div class="d-flex align-items-center gap-3 mt-4">
                <button
                    type="submit"
                    class="btn btn-svtp"
                    :disabled="form.processing || isImpersonating"
                >
                    <span v-if="form.processing" class="spinner-border spinner-border-sm me-2"></span>
                    <i v-else class="bi bi-key me-1"></i>
                    Update Password
                </button>

                <Transition
                    enter-active-class="transition ease-in-out"
                    enter-from-class="opacity-0"
                    leave-active-class="transition ease-in-out"
                    leave-to-class="opacity-0"
                >
                    <span v-if="form.recentlySuccessful" class="small fw-semibold d-inline-flex align-items-center gap-1" style="color: #0E5C61;">
                        <i class="bi bi-check-circle-fill"></i> Saved
                    </span>
                </Transition>
            </div>
        </form>
    </section>
</template>

<style scoped>
.form-control {
    border: 1.5px solid rgba(43,24,16,0.12);
    border-radius: 12px;
    padding: 0.65rem 0.9rem;
    background: #fff;
    transition: border-color 0.2s ease, box-shadow 0.2s ease;
}
.form-control:focus {
    border-color: var(--saffron);
    box-shadow: 0 0 0 0.2rem rgba(249,115,22,0.15);
}
</style>
