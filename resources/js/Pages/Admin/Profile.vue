<script setup>
import { appUrl } from '../../appUrl';
import AdminLayout from '../../Layouts/AdminLayout.vue';
import { Head, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const page = usePage();
const user = computed(() => page.props.auth.user);
const profileForm = useForm({ name: user.value.name ?? '', email: user.value.email ?? '' });
const passwordForm = useForm({ current_password: '', password: '', password_confirmation: '' });
const currentPasswordInput = ref(null);
const passwordInput = ref(null);

function updateProfile() {
    profileForm.patch(appUrl('/admin/profile'), { preserveScroll: true });
}

function updatePassword() {
    passwordForm.put(appUrl('/password'), {
        preserveScroll: true,
        onSuccess: () => passwordForm.reset(),
        onError: () => {
            if (passwordForm.errors.password) {
                passwordForm.reset('password', 'password_confirmation');
                passwordInput.value?.focus();
            }
            if (passwordForm.errors.current_password) {
                passwordForm.reset('current_password');
                currentPasswordInput.value?.focus();
            }
        },
    });
}

function formatDate(value) {
    return value ? new Intl.DateTimeFormat(undefined, { dateStyle: 'medium' }).format(new Date(value)) : 'Not available';
}
</script>

<template>
    <AdminLayout>
        <Head title="Admin Profile" />
        <div class="profile-heading">
            <div><p class="profile-eyebrow">Account</p><h2 class="mb-1">Admin Profile</h2><p class="text-muted mb-0">Manage your account details and password.</p></div>
            <div class="profile-avatar" aria-hidden="true">{{ user.name?.charAt(0).toUpperCase() }}</div>
        </div>

        <div class="row g-4 mt-1">
            <div class="col-12 col-xl-7">
                <section class="card profile-card p-3 p-sm-4">
                    <h4 class="mb-1">Profile information</h4>
                    <p class="text-muted small mb-4">Update the name and email used for this admin account.</p>
                    <form @submit.prevent="updateProfile">
                        <div class="mb-3"><label for="admin-name" class="form-label">Name</label><input id="admin-name" v-model="profileForm.name" class="form-control" type="text" autocomplete="name" required><small class="text-danger">{{ profileForm.errors.name }}</small></div>
                        <div class="mb-3"><label for="admin-email" class="form-label">Email</label><input id="admin-email" v-model="profileForm.email" class="form-control" type="email" autocomplete="username" required><small class="text-danger">{{ profileForm.errors.email }}</small></div>
                        <div class="d-flex align-items-center gap-3"><button class="btn btn-svtp" :disabled="profileForm.processing">Save profile</button><span v-if="profileForm.recentlySuccessful" class="small text-success">Saved.</span></div>
                    </form>
                </section>

                <section class="card profile-card p-3 p-sm-4 mt-4">
                    <h4 class="mb-1">Change password</h4>
                    <p class="text-muted small mb-4">Your current password is required before a new password can be saved.</p>
                    <form @submit.prevent="updatePassword">
                        <div class="mb-3"><label for="current-password" class="form-label">Current password</label><input id="current-password" ref="currentPasswordInput" v-model="passwordForm.current_password" class="form-control" type="password" autocomplete="current-password" required><small class="text-danger">{{ passwordForm.errors.current_password }}</small></div>
                        <div class="row g-3">
                            <div class="col-12 col-md-6"><label for="new-password" class="form-label">New password</label><input id="new-password" ref="passwordInput" v-model="passwordForm.password" class="form-control" type="password" autocomplete="new-password" required><small class="text-danger">{{ passwordForm.errors.password }}</small></div>
                            <div class="col-12 col-md-6"><label for="password-confirmation" class="form-label">Confirm new password</label><input id="password-confirmation" v-model="passwordForm.password_confirmation" class="form-control" type="password" autocomplete="new-password" required><small class="text-danger">{{ passwordForm.errors.password_confirmation }}</small></div>
                        </div>
                        <div class="d-flex align-items-center gap-3 mt-4"><button class="btn btn-svtp" :disabled="passwordForm.processing">Update password</button><span v-if="passwordForm.recentlySuccessful" class="small text-success">Password updated.</span></div>
                    </form>
                </section>
            </div>

            <div class="col-12 col-xl-5">
                <section class="card profile-card p-3 p-sm-4">
                    <h4 class="mb-4">Account details</h4>
                    <dl class="profile-details mb-0">
                        <div><dt>Name</dt><dd>{{ user.name }}</dd></div><div><dt>Email</dt><dd>{{ user.email }}</dd></div>
                        <div><dt>Phone</dt><dd>{{ user.phone || 'Not provided' }}</dd></div><div><dt>Role</dt><dd class="text-capitalize">{{ user.role }}</dd></div>
                        <div><dt>Email status</dt><dd>{{ user.email_verified_at ? 'Verified' : 'Not verified' }}</dd></div><div><dt>Account created</dt><dd>{{ formatDate(user.created_at) }}</dd></div>
                    </dl>
                </section>
            </div>
        </div>
    </AdminLayout>
</template>

<style scoped>
.profile-heading { display: flex; align-items: center; justify-content: space-between; gap: 1rem; }
.profile-eyebrow { margin-bottom: .2rem; color: #fbbf24; font-size: .75rem; font-weight: 700; letter-spacing: .12em; text-transform: uppercase; }
.profile-avatar { display: grid; width: 56px; height: 56px; flex: 0 0 auto; place-items: center; border: 1px solid #475569; border-radius: 50%; background: #26344c; color: #fbbf24; font-size: 1.4rem; font-weight: 700; }
.profile-card { border-radius: .9rem; box-shadow: 0 18px 40px rgba(0,0,0,.16); }
.profile-details { display: grid; }.profile-details > div { display: grid; grid-template-columns: minmax(110px,.8fr) minmax(0,1.2fr); gap: 1rem; padding: .85rem 0; border-bottom: 1px solid #334155; }.profile-details > div:last-child { border-bottom: 0; }
.profile-details dt { color: #94a3b8; font-size: .82rem; font-weight: 500; }.profile-details dd { margin: 0; overflow-wrap: anywhere; color: #f8fafc; text-align: right; }
@media (max-width: 575.98px) { .profile-details > div { grid-template-columns: 1fr; gap: .2rem; }.profile-details dd { text-align: left; } }
</style>
