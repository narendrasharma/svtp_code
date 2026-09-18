<script setup>
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import VendorLayout from '../../Layouts/VendorLayout.vue';
import { appUrl } from '../../appUrl';

const props = defineProps({ profile: Object });

const isActive = computed(() => !!props.profile?.is_active);
const hasWebsite = computed(() => !!props.profile?.website);
const hasDescription = computed(() => !!props.profile?.business_description);

function formatDate(value) {
    return value ? new Date(value).toLocaleDateString('en-IN', { day: 'numeric', month: 'short', year: 'numeric' }) : '—';
}
function entityLabel(value) {
    if (!value) return '—';
    const map = {
        individual: 'Individual',
        sole_proprietor: 'Sole Proprietor',
        partnership: 'Partnership',
        llp: 'LLP',
        private_limited: 'Private Limited',
        public_limited: 'Public Limited',
        other: 'Other',
    };
    return map[value] || value.replace(/_/g, ' ').replace(/\b\w/g, c => c.toUpperCase());
}
</script>

<template>
    <VendorLayout>
        <div class="vendor-profile-header">
            <div>
                <p class="vendor-profile-eyebrow">Vendor Account</p>
                <h1 class="vendor-profile-title">{{ profile.business_name }}</h1>
                <p class="vendor-profile-subtitle">{{ entityLabel(profile.entity_type) }} <span class="vendor-profile-dot">·</span> {{ profile.country_code }}</p>
            </div>
            <div class="vendor-profile-actions">
                <span class="badge vendor-badge" :class="isActive ? 'vendor-badge--active' : 'vendor-badge--inactive'">{{ isActive ? 'Active' : 'Inactive' }}</span>
                <span v-if="profile.verification_status" class="badge vendor-badge vendor-badge--kyc">KYC: {{ profile.verification_status }}</span>
                <Link :href="appUrl('/vendor/profile/edit')" class="btn btn-svtp btn-sm">Edit Profile</Link>
            </div>
        </div>

        <div class="vendor-profile-grid">
            <!-- Business Information -->
            <section class="vendor-card">
                <div class="vendor-card-header">
                    <span class="vendor-card-icon vendor-card-icon--business"><i class="bi bi-briefcase-fill"></i></span>
                    <h2 class="vendor-card-title">Business Information</h2>
                </div>
                <div class="vendor-card-body">
                    <div class="vendor-field">
                        <span class="vendor-field-label">Business Name</span>
                        <span class="vendor-field-value">{{ profile.business_name }}</span>
                    </div>
                    <div class="vendor-field">
                        <span class="vendor-field-label">Entity Type</span>
                        <span class="vendor-field-value">{{ entityLabel(profile.entity_type) }}</span>
                    </div>
                    <div v-if="hasDescription" class="vendor-field vendor-field--full">
                        <span class="vendor-field-label">Description</span>
                        <span class="vendor-field-value vendor-field-value--multiline">{{ profile.business_description }}</span>
                    </div>
                </div>
            </section>

            <!-- Contact Information -->
            <section class="vendor-card">
                <div class="vendor-card-header">
                    <span class="vendor-card-icon vendor-card-icon--contact"><i class="bi bi-telephone-fill"></i></span>
                    <h2 class="vendor-card-title">Contact Information</h2>
                </div>
                <div class="vendor-card-body">
                    <div class="vendor-field">
                        <span class="vendor-field-label">Phone</span>
                        <a :href="`tel:${profile.phone}`" class="vendor-field-value vendor-field-value--link">{{ profile.phone || '—' }}</a>
                    </div>
                    <div class="vendor-field">
                        <span class="vendor-field-label">Email</span>
                        <a :href="`mailto:${profile.email}`" class="vendor-field-value vendor-field-value--link">{{ profile.email || '—' }}</a>
                    </div>
                    <div v-if="hasWebsite" class="vendor-field">
                        <span class="vendor-field-label">Website</span>
                        <a :href="profile.website" target="_blank" rel="noopener" class="vendor-field-value vendor-field-value--link">{{ profile.website }}</a>
                    </div>
                </div>
            </section>

            <!-- Address -->
            <section class="vendor-card">
                <div class="vendor-card-header">
                    <span class="vendor-card-icon vendor-card-icon--address"><i class="bi bi-geo-alt-fill"></i></span>
                    <h2 class="vendor-card-title">Address</h2>
                </div>
                <div class="vendor-card-body">
                    <p class="vendor-address">
                        {{ profile.address || '—' }}<br />
                        <span v-if="profile.city || profile.state">{{ profile.city }}<span v-if="profile.city && profile.state">, </span>{{ profile.state }}</span><br v-if="profile.postcode || profile.country_code" />
                        <span v-if="profile.postcode">{{ profile.postcode }}</span><span v-if="profile.postcode && profile.country_code"> · </span><span v-if="profile.country_code">{{ profile.country_code }}</span>
                    </p>
                </div>
            </section>

            <!-- Account -->
            <section class="vendor-card">
                <div class="vendor-card-header">
                    <span class="vendor-card-icon vendor-card-icon--account"><i class="bi bi-shield-check"></i></span>
                    <h2 class="vendor-card-title">Account & Verification</h2>
                </div>
                <div class="vendor-card-body">
                    <div class="vendor-field">
                        <span class="vendor-field-label">Account Status</span>
                        <span class="badge" :class="isActive ? 'bg-success' : 'bg-secondary'">{{ isActive ? 'Active' : 'Inactive' }}</span>
                    </div>
                    <div class="vendor-field">
                        <span class="vendor-field-label">Approved At</span>
                        <span class="vendor-field-value">{{ formatDate(profile.approved_at) }}</span>
                    </div>
                    <div v-if="profile.verification_status" class="vendor-field">
                        <span class="vendor-field-label">KYC Status</span>
                        <span class="badge vendor-badge" :class="profile.verification_status === 'verified' ? 'vendor-badge--verified' : 'vendor-badge--pending'">{{ profile.verification_status }}</span>
                    </div>
                    <div class="vendor-field">
                        <span class="vendor-field-label">Application</span>
                        <Link :href="appUrl('/vendor/application')" class="vendor-field-value vendor-field-value--link">View Application & KYC</Link>
                    </div>
                </div>
            </section>
        </div>

        <p class="vendor-profile-note">Business contact information is editable. Verification status, role and approval details are managed by administrators.</p>
    </VendorLayout>
</template>

<style scoped>
.vendor-profile-header {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 1rem;
    margin-bottom: 1.5rem;
    flex-wrap: wrap;
}
.vendor-profile-eyebrow {
    font-size: 0.68rem;
    font-weight: 700;
    letter-spacing: 0.12em;
    text-transform: uppercase;
    color: #38bdf8;
    margin: 0 0 0.25rem;
}
.vendor-profile-title {
    font-size: 1.65rem;
    font-weight: 700;
    color: #f8fafc;
    margin: 0;
    line-height: 1.2;
}
.vendor-profile-subtitle {
    font-size: 0.82rem;
    color: #94a3b8;
    margin: 0.25rem 0 0;
}
.vendor-profile-dot { color: #475569; margin: 0 0.2rem; }
.vendor-profile-actions {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    flex-wrap: wrap;
}
.vendor-badge {
    font-size: 0.68rem;
    font-weight: 700;
    letter-spacing: 0.04em;
    text-transform: uppercase;
    padding: 0.35rem 0.6rem;
    border-radius: 999px;
}
.vendor-badge--active { background: #065f46; color: #a7f3d0; border: 1px solid #047857; }
.vendor-badge--inactive { background: #334155; color: #cbd5e1; }
.vendor-badge--kyc { background: #1e293b; color: #cbd5e1; border: 1px solid #334155; }
.vendor-badge--verified { background: #065f46; color: #a7f3d0; }
.vendor-badge--pending { background: #78350f; color: #fde68a; border: 1px solid #92400e; }

.vendor-profile-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 1rem;
}
@media (max-width: 767px) {
    .vendor-profile-grid { grid-template-columns: 1fr; }
}
.vendor-card {
    background: #111c2d;
    border: 1px solid #1e293b;
    border-radius: 16px;
    overflow: hidden;
    box-shadow: 0 8px 24px rgba(0,0,0,0.18);
}
.vendor-card-header {
    display: flex;
    align-items: center;
    gap: 0.7rem;
    padding: 1rem 1.25rem 0.85rem;
    border-bottom: 1px solid #1e293b;
}
.vendor-card-icon {
    width: 32px; height: 32px; border-radius: 8px; display: grid; place-items: center; font-size: 0.9rem; flex: 0 0 32px;
}
.vendor-card-icon--business { background: rgba(56,189,248,0.12); color: #38bdf8; }
.vendor-card-icon--contact { background: rgba(251,146,60,0.12); color: #fb923c; }
.vendor-card-icon--address { background: rgba(167,243,208,0.12); color: #34d399; }
.vendor-card-icon--account { background: rgba(196,181,253,0.12); color: #c4b5fd; }
.vendor-card-title {
    font-size: 0.92rem;
    font-weight: 600;
    color: #f8fafc;
    margin: 0;
}
.vendor-card-body {
    padding: 1rem 1.25rem 1.25rem;
    display: grid;
    gap: 0.85rem;
}
.vendor-field {
    display: grid;
    gap: 0.2rem;
}
.vendor-field--full { grid-column: 1 / -1; }
.vendor-field-label {
    font-size: 0.68rem;
    font-weight: 600;
    letter-spacing: 0.06em;
    text-transform: uppercase;
    color: #94a3b8;
}
.vendor-field-value {
    font-size: 0.88rem;
    font-weight: 500;
    color: #f1f5f9;
    line-height: 1.5;
    overflow-wrap: anywhere;
}
.vendor-field-value--multiline {
    color: #cbd5e1;
    font-weight: 400;
}
.vendor-field-value--link {
    color: #38bdf8;
    text-decoration: none;
}
.vendor-field-value--link:hover {
    color: #7dd3fc;
    text-decoration: underline;
}
.vendor-address {
    font-size: 0.88rem;
    color: #f1f5f9;
    line-height: 1.6;
    margin: 0;
}
.vendor-profile-note {
    font-size: 0.72rem;
    color: #94a3b8;
    margin: 1.25rem 0 0;
    text-align: center;
}
</style>
