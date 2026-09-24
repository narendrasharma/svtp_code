<script setup>
import AccountLayout from '@/Layouts/AccountLayout.vue';
import DeleteUserForm from './Partials/DeleteUserForm.vue';
import UpdatePasswordForm from './Partials/UpdatePasswordForm.vue';
import UpdateProfileInformationForm from './Partials/UpdateProfileInformationForm.vue';
import SeoHead from '@/Components/SeoHead.vue';

defineProps({
    mustVerifyEmail: {
        type: Boolean,
    },
    status: {
        type: String,
    },
});
</script>

<template>
    <AccountLayout>
        <SeoHead title="Profile" noindex private-page />
        <div class="container py-5">
            <h1 class="section-title mb-1">Profile Settings</h1>
            <p class="text-muted small mb-4">Manage your personal information, password and account preferences.</p>


            <div class="account-profile-content">
                <div class="space-y-4 d-flex flex-column gap-4">
                    <div class="profile-card">
                        <UpdateProfileInformationForm
                            :must-verify-email="mustVerifyEmail"
                            :status="status"
                        />
                    </div>

                    <div class="profile-card">
                        <UpdatePasswordForm />
                    </div>

                    <div class="profile-card profile-card--danger">
                        <DeleteUserForm />
                    </div>
                </div>
            </div>
        </div>
    </AccountLayout>
</template>

<style scoped>
.profile-card {
    background: #fff;
    border: 1px solid rgba(43,24,16,0.08);
    border-radius: 20px;
    padding: 1.5rem;
    box-shadow: 0 12px 28px rgba(58,10,24,0.06);
    transition: box-shadow 0.2s ease;
}
.profile-card:hover {
    box-shadow: 0 18px 36px rgba(58,10,24,0.09);
}
@media (min-width: 768px) {
    .profile-card {
        padding: 1.75rem 2rem;
    }
}
.profile-card--danger {
    border-color: rgba(220,38,38,0.14);
    background: linear-gradient(180deg, #fff 0%, rgba(254,242,242,0.55) 100%);
}
</style>
