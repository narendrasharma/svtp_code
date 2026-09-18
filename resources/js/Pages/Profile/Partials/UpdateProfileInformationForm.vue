<script setup>
import InputError from '@/Components/InputError.vue';
import { Link, useForm, usePage } from '@inertiajs/vue3';
import { appUrl } from '@/appUrl.js';
import { computed } from 'vue';

defineProps({
    mustVerifyEmail: {
        type: Boolean,
    },
    status: {
        type: String,
    },
});

const user = usePage().props.auth.user;

const form = useForm({
    name: user.name,
    email: user.email,
    phone: user.phone ?? '',
    marketing_email_opt_in: user.marketing_email_opt_in ?? true,
    marketing_sms_opt_in: user.marketing_sms_opt_in ?? true,
    marketing_whatsapp_opt_in: user.marketing_whatsapp_opt_in ?? true,
});

const isDirty = computed(() => form.isDirty);
</script>

<template>
    <section>
        <header class="mb-4">
            <div class="d-flex align-items-center gap-2 mb-1">
                <span class="d-inline-flex align-items-center justify-content-center rounded-circle flex-shrink-0" style="width: 36px; height: 36px; background: var(--festive-gradient-soft); border: 1px solid rgba(201,162,39,0.25);"><i class="bi bi-person-lines-fill" style="color: var(--maroon);"></i></span>
                <h3 class="h5 mb-0" style="font-family: var(--font-display); color: var(--maroon);">Profile Information</h3>
            </div>
            <p class="small text-muted mb-0">Update your name, email and phone. This information is used for bookings and vendor communication.</p>
        </header>

        <form @submit.prevent="form.patch(appUrl('/account/profile'))" class="mt-4">
            <div class="row g-3">
                <div class="col-12 col-md-6">
                    <label for="name" class="form-label small fw-semibold" style="color: var(--ink);">Full Name <span class="text-danger">*</span></label>
                    <input
                        id="name"
                        type="text"
                        class="form-control"
                        v-model="form.name"
                        required
                        autocomplete="name"
                        placeholder="Your full name"
                        :class="{ 'is-invalid': form.errors.name }"
                    />
                    <InputError class="mt-1" :message="form.errors.name" />
                </div>

                <div class="col-12 col-md-6">
                    <label for="email" class="form-label small fw-semibold" style="color: var(--ink);">Email Address <span class="text-danger">*</span></label>
                    <input
                        id="email"
                        type="email"
                        class="form-control"
                        v-model="form.email"
                        required
                        autocomplete="username"
                        placeholder="you@example.com"
                        :class="{ 'is-invalid': form.errors.email }"
                    />
                    <InputError class="mt-2" :message="form.errors.email" />
                </div>

                <div class="col-12 col-md-6">
                    <label for="phone" class="form-label small fw-semibold" style="color: var(--ink);">Phone <span class="text-muted fw-normal">(optional)</span></label>
                    <input
                        id="phone"
                        type="text"
                        class="form-control"
                        v-model="form.phone"
                        autocomplete="tel"
                        placeholder="+91 9XXXXXXXXX"
                        :class="{ 'is-invalid': form.errors.phone }"
                    />
                    <InputError class="mt-2" :message="form.errors.phone" />
                    <div class="form-text small text-muted">Used for booking confirmations and support.</div>
                </div>
            </div>

            <div class="card mt-4 p-3">
                <h4 class="h6 mb-1">Marketing preferences</h4>
                <p class="small text-muted mb-3">Promotional email, SMS and WhatsApp only. Booking confirmations, security and account mail are always sent.</p>
                <div class="form-check mb-2">
                    <input id="marketing-email" v-model="form.marketing_email_opt_in" type="checkbox" class="form-check-input" />
                    <label for="marketing-email" class="form-check-label small">Promotional emails</label>
                </div>
                <div class="form-check mb-2">
                    <input id="marketing-sms" v-model="form.marketing_sms_opt_in" type="checkbox" class="form-check-input" />
                    <label for="marketing-sms" class="form-check-label small">Promotional SMS</label>
                </div>
                <div class="form-check">
                    <input id="marketing-whatsapp" v-model="form.marketing_whatsapp_opt_in" type="checkbox" class="form-check-input" />
                    <label for="marketing-whatsapp" class="form-check-label small">Promotional WhatsApp messages</label>
                </div>
            </div>

            <div v-if="mustVerifyEmail && user.email_verified_at === null" class="alert alert-warning d-flex align-items-start gap-2 mt-4 py-2 small">
                <i class="bi bi-exclamation-triangle mt-1"></i>
                <div>
                    Your email address is unverified.
                    <Link
                        :href="appUrl('/email/verification-notification')"
                        method="post"
                        as="button"
                        class="btn btn-link btn-sm p-0 align-baseline"
                        style="color: var(--maroon); text-decoration: underline;"
                    >
                        Click here to re-send the verification email.
                    </Link>
                    <div
                        v-show="status === 'verification-link-sent'"
                        class="mt-1 text-success fw-semibold"
                    >
                        A new verification link has been sent.
                    </div>
                </div>
            </div>

            <div class="d-flex align-items-center gap-3 mt-4">
                <button
                    type="submit"
                    class="btn btn-svtp"
                    :disabled="form.processing || !isDirty"
                    :class="{ 'opacity-50': !isDirty && !form.processing }"
                >
                    <span v-if="form.processing" class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>
                    <i v-else class="bi bi-check2 me-1"></i>
                    Save Changes
                </button>

                <Transition
                    enter-active-class="transition ease-in-out"
                    enter-from-class="opacity-0"
                    leave-active-class="transition ease-in-out"
                    leave-to-class="opacity-0"
                >
                    <span
                        v-if="form.recentlySuccessful"
                        class="small fw-semibold d-inline-flex align-items-center gap-1"
                        style="color: #0E5C61;"
                    >
                        <i class="bi bi-check-circle-fill"></i> Saved
                    </span>
                </Transition>
                <span v-if="!isDirty && !form.processing" class="small text-muted">No changes</span>
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
    background: #fff;
}
.form-control.is-invalid:focus {
    box-shadow: 0 0 0 0.2rem rgba(220,38,38,0.12);
}
</style>
