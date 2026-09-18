<script setup>
import AdminLayout from '../../../Layouts/AdminLayout.vue';
import { appUrl } from '../../../appUrl';
import { useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps({
    settings: {
        type: Object,
        default: () => ({}),
    },
});
const activeSetting = ref('basic');


const form = useForm({
    logo: null,
    favicon: null,
});

const logoPreview = ref(null);
const faviconPreview = ref(null);

function selectLogo(event) {
    const file = event.target.files[0];

    form.logo = file ?? null;

    if (file) {
        logoPreview.value = URL.createObjectURL(file);
    }
}

function selectFavicon(event) {
    const file = event.target.files[0];

    form.favicon = file ?? null;

    if (file) {
        faviconPreview.value = URL.createObjectURL(file);
    }
}

function submitLogoSettings() {
    form.post(
        appUrl('/admin/settings/logo'),
        {
            forceFormData: true,
            preserveScroll: true,

            onSuccess: () => {
                form.logo = null;
                form.favicon = null;
            },
        }
    );
}

function submitBasicSettings() {
    basicForm.post(
        appUrl('/admin/settings/basic'),
        {
            preserveScroll: true,
        }
    );
}


function submitContactSettings() {
    contactForm.post(
        appUrl('/admin/settings/contact'),
        {
            preserveScroll: true,
        }
    );
}


function submitSocialSettings() {
    socialForm.post(
        appUrl('/admin/settings/social'),
        {
            preserveScroll: true,
        }
    );
}


function submitSeoSettings() {
    seoForm.post(
        appUrl('/admin/settings/seo'),
        {
            forceFormData: true,
            preserveScroll: true,
        }
    );
}

const settingNavigation = [
    {
        key: 'basic',
        label: 'Basic Settings',
        icon: 'bi-sliders',
        enabled: true,
    },

    {
        key: 'logo',
        label: 'Logo Settings',
        icon: 'bi-image',
        enabled: true,
    },

    {
        key: 'contact',
        label: 'Contact Settings',
        icon: 'bi-telephone',
        enabled: true,
    },

    {
        key: 'social',
        label: 'Social Links',
        icon: 'bi-share',
        enabled: true,
    },

    {
        key: 'footer',
        label: 'Footer Settings',
        icon: 'bi-layout-text-window-reverse',
        enabled: false,
    },

    {
        key: 'email',
        label: 'Email Settings',
        icon: 'bi-envelope',
        enabled: false,
    },

    {
        key: 'payment',
        label: 'Payment Settings',
        icon: 'bi-credit-card',
        enabled: false,
    },

    {
        key: 'marketplace',
        label: 'Marketplace',
        icon: 'bi-shop',
        enabled: true,
    },

    {
        key: 'seo',
        label: 'SEO Settings',
        icon: 'bi-search',
        enabled: true,
    },

    {
        key: 'map',
        label: 'Map Settings',
        icon: 'bi-geo-alt',
        enabled: false,
    },

    {
        key: 'ai',
        label: 'AI Settings',
        icon: 'bi-stars',
        enabled: false,
    },
];

const basicForm = useForm({
    site_name: props.settings.site_name ?? '',
    site_tagline: props.settings.site_tagline ?? '',
    copyright_text: props.settings.copyright_text ?? '',
});


const contactForm = useForm({
    primary_phone: props.settings.primary_phone ?? '',
    secondary_phone: props.settings.secondary_phone ?? '',
    contact_email: props.settings.contact_email ?? '',
    website_url: props.settings.website_url ?? '',
    office_address: props.settings.office_address ?? '',
    google_maps_url: props.settings.google_maps_url ?? '',
});


const socialForm = useForm({
    facebook_url: props.settings.facebook_url ?? '',
    instagram_url: props.settings.instagram_url ?? '',
    youtube_url: props.settings.youtube_url ?? '',
    whatsapp_number: props.settings.whatsapp_number ?? '',
    whatsapp_message: props.settings.whatsapp_message ?? '',
});


const marketplaceForm = useForm({
    platform_commission_percentage: props.settings.platform_commission_percentage ?? '10.00',
    minimum_withdrawal_amount: props.settings.minimum_withdrawal_amount ?? '1000.00',
});


function submitMarketplaceSettings() {
    marketplaceForm.post(
        appUrl('/admin/settings/marketplace'),
        {
            preserveScroll: true,
        }
    );
}


const seoForm = useForm({

    seo_meta_title:
        props.settings.seo_meta_title ?? '',

    seo_meta_description:
        props.settings.seo_meta_description ?? '',

    seo_meta_keywords:
        props.settings.seo_meta_keywords ?? '',

    seo_og_image: null,
    remove_seo_og_image: false,

    seo_index:
        props.settings.seo_index === '1',

    seo_follow:
        props.settings.seo_follow === '1',

    google_site_verification:
        props.settings.google_site_verification ?? '',
});


const seoOgPreview = ref(null);
function removeSeoOgImage() {
    seoForm.seo_og_image = null;
    seoForm.remove_seo_og_image = true;
    seoOgPreview.value = null;
}
function selectSeoOgImage(event) {
    const file = event.target.files[0];

    seoForm.seo_og_image = file ?? null;
    seoForm.remove_seo_og_image = false;

    if (file) {
        seoOgPreview.value = URL.createObjectURL(file);
    }
}

</script>

<template>
    <AdminLayout>

        <div class="settings-header mb-4">
            <div>
                <div class="text-muted small mb-1">
                    Admin / Settings
                </div>

                <h2 class="mb-1">
                    Settings
                </h2>

                <p class="text-muted mb-0">
                    Configure your website and system settings.
                </p>
            </div>
        </div>

        <div class="row g-4">

            <!-- LEFT SETTINGS MENU -->
            <div class="col-lg-3">



                <div class="card settings-menu">

                    <div class="card-header">
                        <strong>Settings Panel</strong>
                    </div>

                    <div class="card-body p-2">

                            <div
                            v-for="item in settingNavigation"
                            :key="item.key"
                            class="settings-menu-item"
                            :class="{
                            active: item.enabled && activeSetting === item.key,
                            disabled: !item.enabled
                            }"
                            @click="item.enabled && (activeSetting = item.key)"
                            >
                            <i
                            class="bi"
                            :class="item.icon"
                            ></i>

                            <span>
                            {{ item.label }}
                            </span>

                            <span
                            v-if="!item.enabled"
                            class="ms-auto badge text-bg-secondary"
                            >
                            Soon
                            </span>
                            </div>

                    </div>

                </div>

            </div>


            <!-- RIGHT CONTENT -->
            <div class="col-lg-9">


                <form
                    v-if="activeSetting === 'seo'"
                    @submit.prevent="submitSeoSettings"
                >
                    <div class="card">

                        <div class="card-header">
                            <div class="d-flex align-items-center gap-2">
                                <i class="bi bi-search"></i>
                                <strong>SEO Settings</strong>
                            </div>
                        </div>

                        <div class="card-body">

                            <div class="row g-4">

                                <div class="col-12">

                                    <label class="form-label fw-semibold">
                                        Default Meta Title
                                    </label>

                                    <input
                                        v-model="seoForm.seo_meta_title"
                                        type="text"
                                        class="form-control"
                                        maxlength="70"
                                        placeholder="Best Tour & Travel Booking Platform"
                                    />

                                    <small class="text-muted">
                                        Recommended around 50–60 characters.
                                    </small>

                                </div>


                                <div class="col-12">

                                    <label class="form-label fw-semibold">
                                        Default Meta Description
                                    </label>

                                    <textarea
                                        v-model="seoForm.seo_meta_description"
                                        class="form-control"
                                        rows="3"
                                        maxlength="170"
                                        placeholder="Discover tours, destinations and travel packages..."
                                    ></textarea>

                                    <small class="text-muted">
                                        Recommended around 150–160 characters.
                                    </small>

                                </div>


                                <div class="col-12">

                                    <label class="form-label fw-semibold">
                                        Meta Keywords
                                    </label>

                                    <input
                                        v-model="seoForm.seo_meta_keywords"
                                        type="text"
                                        class="form-control"
                                        placeholder="tour, travel, booking, holiday"
                                    />

                                    <small class="text-muted">
                                        Optional. Modern search engines place little importance on meta keywords.
                                    </small>

                                </div>


                                <div class="col-6">
                                    <label class="form-label fw-semibold">
                                        Default Social Share Image
                                    </label>

                                    <div class="mb-3">
                                        <img
                                            v-if="seoOgPreview"
                                            :src="seoOgPreview"
                                            alt="Social share preview"
                                            class="img-fluid rounded border"
                                            style="max-width: 320px; max-height: 180px; object-fit: cover;"
                                        >

                                        <img
                                            v-else-if="
                settings.seo_og_image &&
                !seoForm.remove_seo_og_image
            "
                                            :src="
                `${appUrl('/storage')}/${settings.seo_og_image}`
            "
                                            alt="Current social share image"
                                            class="img-fluid rounded border"
                                            style="max-width: 320px; max-height: 180px; object-fit: cover;"
                                        >

                                        <div
                                            v-else
                                            class="border rounded p-4 text-muted text-center"
                                            style="max-width: 320px;"
                                        >
                                            <i class="bi bi-image fs-2 d-block mb-2"></i>

                                            No custom social share image uploaded.
                                        </div>
                                    </div>

                                    <input
                                        type="file"
                                        class="form-control"
                                        accept=".jpg,.jpeg,.png,.webp"
                                        @change="selectSeoOgImage"
                                    >

                                    <div class="form-text">
                                        Recommended size: 1200 × 630 px.
                                    </div>

                                    <button
                                        v-if="
            settings.seo_og_image &&
            !seoForm.remove_seo_og_image
        "
                                        type="button"
                                        class="btn btn-sm btn-outline-danger mt-2"
                                        @click="removeSeoOgImage"
                                    >
                                        <i class="bi bi-trash me-1"></i>
                                        Remove Current Image
                                    </button>

                                    <div
                                        v-if="seoForm.remove_seo_og_image"
                                        class="alert alert-warning py-2 mt-2 mb-0"
                                    >
                                        The current social share image will be removed
                                        when you save the SEO settings.
                                    </div>

                                    <div
                                        v-if="seoForm.errors.seo_og_image"
                                        class="text-danger small mt-1"
                                    >
                                        {{ seoForm.errors.seo_og_image }}
                                    </div>
                                </div>


                                <div class="col-md-6">

                                    <label class="form-label fw-semibold">
                                        Google Site Verification
                                    </label>

                                    <input
                                        v-model="seoForm.google_site_verification"
                                        type="text"
                                        class="form-control"
                                        placeholder="Verification token"
                                    />

                                </div>


                                <div class="col-md-6">

                                    <div class="form-check form-switch">

                                        <input
                                            v-model="seoForm.seo_index"
                                            class="form-check-input"
                                            type="checkbox"
                                            id="seoIndex"
                                        >

                                        <label
                                            class="form-check-label"
                                            for="seoIndex"
                                        >
                                            Allow search engines to index website
                                        </label>

                                    </div>

                                </div>


                                <div class="col-md-6">

                                    <div class="form-check form-switch">

                                        <input
                                            v-model="seoForm.seo_follow"
                                            class="form-check-input"
                                            type="checkbox"
                                            id="seoFollow"
                                        >

                                        <label
                                            class="form-check-label"
                                            for="seoFollow"
                                        >
                                            Allow search engines to follow links
                                        </label>

                                    </div>

                                </div>

                            </div>


                            <hr class="my-4">


                            <button
                                type="submit"
                                class="btn btn-warning"
                                :disabled="seoForm.processing"
                            >

                <span v-if="seoForm.processing">
                    Saving...
                </span>

                                <span v-else>
                    <i class="bi bi-check-lg me-1"></i>
                    Save SEO Settings
                </span>

                            </button>

                        </div>

                    </div>
                </form>

                <form
                    v-if="activeSetting === 'basic'"
                    @submit.prevent="submitBasicSettings"
                >
                    <div class="card">

                        <div class="card-header">
                            <div class="d-flex align-items-center gap-2">
                                <i class="bi bi-sliders"></i>
                                <strong>Basic Settings</strong>
                            </div>
                        </div>


                        <div class="card-body">

                            <div class="row g-4">

                                <div class="col-md-6">

                                    <label class="form-label fw-semibold">
                                        Site Name
                                        <span class="text-danger">*</span>
                                    </label>

                                    <input
                                        v-model="basicForm.site_name"
                                        type="text"
                                        class="form-control"
                                        placeholder="Your travel agency name"
                                    />

                                    <div
                                        v-if="basicForm.errors.site_name"
                                        class="text-danger small mt-1"
                                    >
                                        {{ basicForm.errors.site_name }}
                                    </div>

                                </div>


                                <div class="col-md-6">

                                    <label class="form-label fw-semibold">
                                        Site Tagline
                                    </label>

                                    <input
                                        v-model="basicForm.site_tagline"
                                        type="text"
                                        class="form-control"
                                        placeholder="Your website tagline"
                                    />

                                    <div
                                        v-if="basicForm.errors.site_tagline"
                                        class="text-danger small mt-1"
                                    >
                                        {{ basicForm.errors.site_tagline }}
                                    </div>

                                </div>


                                <div class="col-12">

                                    <label class="form-label fw-semibold">
                                        Copyright Text
                                    </label>

                                    <input
                                        v-model="basicForm.copyright_text"
                                        type="text"
                                        class="form-control"
                                        placeholder="All rights reserved."
                                    />

                                </div>

                            </div>


                            <hr class="my-4">


                            <button
                                class="btn btn-warning"
                                type="submit"
                                :disabled="basicForm.processing"
                            >
                <span v-if="basicForm.processing">
                    Saving...
                </span>

                                <span v-else>
                    <i class="bi bi-check-lg me-1"></i>
                    Save Basic Settings
                </span>
                            </button>

                        </div>

                    </div>
                </form>


                <form
                    v-if="activeSetting === 'contact'"
                    @submit.prevent="submitContactSettings"
                >
                    <div class="card">

                        <div class="card-header">
                            <div class="d-flex align-items-center gap-2">
                                <i class="bi bi-telephone"></i>
                                <strong>Contact Settings</strong>
                            </div>
                        </div>


                        <div class="card-body">

                            <div class="row g-4">

                                <div class="col-md-6">

                                    <label class="form-label fw-semibold">
                                        Primary Phone
                                    </label>

                                    <input
                                        v-model="contactForm.primary_phone"
                                        type="text"
                                        class="form-control"
                                        placeholder="+91 89234 27393"
                                    />

                                </div>


                                <div class="col-md-6">

                                    <label class="form-label fw-semibold">
                                        Secondary Phone
                                    </label>

                                    <input
                                        v-model="contactForm.secondary_phone"
                                        type="text"
                                        class="form-control"
                                        placeholder="+91 90843 97393"
                                    />

                                </div>


                                <div class="col-md-6">

                                    <label class="form-label fw-semibold">
                                        Email Address
                                    </label>

                                    <input
                                        v-model="contactForm.contact_email"
                                        type="email"
                                        class="form-control"
                                        placeholder="info@example.com"
                                    />

                                    <div
                                        v-if="contactForm.errors.contact_email"
                                        class="text-danger small mt-1"
                                    >
                                        {{ contactForm.errors.contact_email }}
                                    </div>

                                </div>


                                <div class="col-md-6">

                                    <label class="form-label fw-semibold">
                                        Website URL
                                    </label>

                                    <input
                                        v-model="contactForm.website_url"
                                        type="text"
                                        class="form-control"
                                        placeholder="https://example.com"
                                    />

                                </div>


                                <div class="col-12">

                                    <label class="form-label fw-semibold">
                                        Office Address
                                    </label>

                                    <textarea
                                        v-model="contactForm.office_address"
                                        class="form-control"
                                        rows="3"
                                        placeholder="Enter complete office address"
                                    ></textarea>

                                </div>


                                <div class="col-12">

                                    <label class="form-label fw-semibold">
                                        Google Maps URL
                                    </label>

                                    <input
                                        v-model="contactForm.google_maps_url"
                                        type="text"
                                        class="form-control"
                                        placeholder="https://maps.google.com/..."
                                    />

                                    <small class="text-muted">
                                        Link users should open when they click
                                        "Get Directions".
                                    </small>

                                </div>

                            </div>


                            <hr class="my-4">


                            <button
                                type="submit"
                                class="btn btn-warning"
                                :disabled="contactForm.processing"
                            >
                <span v-if="contactForm.processing">
                    Saving...
                </span>

                                <span v-else>
                    <i class="bi bi-check-lg me-1"></i>
                    Save Contact Settings
                </span>
                            </button>

                        </div>

                    </div>
                </form>


                <form
                    v-if="activeSetting === 'social'"
                    @submit.prevent="submitSocialSettings"
                >
                    <div class="card">

                        <div class="card-header">

                            <div class="d-flex align-items-center gap-2">
                                <i class="bi bi-share"></i>
                                <strong>Social & WhatsApp Settings</strong>
                            </div>

                        </div>


                        <div class="card-body">

                            <div class="row g-4">

                                <div class="col-md-6">

                                    <label class="form-label fw-semibold">
                                        Facebook URL
                                    </label>

                                    <div class="input-group">

                        <span class="input-group-text">
                            <i class="bi bi-facebook"></i>
                        </span>

                                        <input
                                            v-model="socialForm.facebook_url"
                                            type="text"
                                            class="form-control"
                                            placeholder="https://facebook.com/..."
                                        />

                                    </div>

                                </div>


                                <div class="col-md-6">

                                    <label class="form-label fw-semibold">
                                        Instagram URL
                                    </label>

                                    <div class="input-group">

                        <span class="input-group-text">
                            <i class="bi bi-instagram"></i>
                        </span>

                                        <input
                                            v-model="socialForm.instagram_url"
                                            type="text"
                                            class="form-control"
                                            placeholder="https://instagram.com/..."
                                        />

                                    </div>

                                </div>


                                <div class="col-md-6">

                                    <label class="form-label fw-semibold">
                                        YouTube URL
                                    </label>

                                    <div class="input-group">

                        <span class="input-group-text">
                            <i class="bi bi-youtube"></i>
                        </span>

                                        <input
                                            v-model="socialForm.youtube_url"
                                            type="text"
                                            class="form-control"
                                            placeholder="https://youtube.com/..."
                                        />

                                    </div>

                                </div>


                                <div class="col-md-6">

                                    <label class="form-label fw-semibold">
                                        WhatsApp Number
                                    </label>

                                    <div class="input-group">

                        <span class="input-group-text">
                            <i class="bi bi-whatsapp"></i>
                        </span>

                                        <input
                                            v-model="socialForm.whatsapp_number"
                                            type="text"
                                            class="form-control"
                                            placeholder="918923427393"
                                        />

                                    </div>

                                    <small class="text-muted">
                                        Country code + number without spaces or + sign.
                                    </small>

                                </div>


                                <div class="col-12">

                                    <label class="form-label fw-semibold">
                                        Default WhatsApp Message
                                    </label>

                                    <textarea
                                        v-model="socialForm.whatsapp_message"
                                        class="form-control"
                                        rows="3"
                                        placeholder="Default message..."
                                    ></textarea>

                                </div>

                            </div>


                            <hr class="my-4">


                            <button
                                type="submit"
                                class="btn btn-warning"
                                :disabled="socialForm.processing"
                            >
                <span v-if="socialForm.processing">
                    Saving...
                </span>

                                <span v-else>
                    <i class="bi bi-check-lg me-1"></i>
                    Save Social Settings
                </span>
                            </button>

                        </div>

                    </div>
                </form>



                <form
                    v-if="activeSetting === 'marketplace'"
                    @submit.prevent="submitMarketplaceSettings"
                >
                    <div class="card">

                        <div class="card-header">
                            <div class="d-flex align-items-center gap-2">
                                <i class="bi bi-shop"></i>
                                <strong>Marketplace Settings</strong>
                            </div>
                        </div>


                        <div class="card-body">

                            <div class="row g-4">

                                <div class="col-md-6">

                                    <label class="form-label fw-semibold">
                                        Platform Commission (%)
                                    </label>

                                    <input
                                        v-model="marketplaceForm.platform_commission_percentage"
                                        type="number"
                                        step="0.01"
                                        min="0"
                                        max="100"
                                        class="form-control"
                                        placeholder="10.00"
                                    />

                                    <div class="form-text">
                                        Percentage retained by the platform from new vendor bookings.
                                        Changes apply only to future bookings.
                                    </div>

                                    <div
                                        v-if="marketplaceForm.errors.platform_commission_percentage"
                                        class="text-danger small mt-1"
                                    >
                                        {{ marketplaceForm.errors.platform_commission_percentage }}
                                    </div>

                                </div>

                                <div class="col-md-6">

                                    <label class="form-label fw-semibold">
                                        Minimum Withdrawal Amount (₹)
                                    </label>

                                    <input
                                        v-model="marketplaceForm.minimum_withdrawal_amount"
                                        type="number"
                                        step="0.01"
                                        min="0"
                                        class="form-control"
                                        placeholder="1000.00"
                                    />

                                    <div class="form-text">
                                        Vendors cannot request payouts below this amount.
                                        Checked server-side against available balance.
                                    </div>

                                    <div
                                        v-if="marketplaceForm.errors.minimum_withdrawal_amount"
                                        class="text-danger small mt-1"
                                    >
                                        {{ marketplaceForm.errors.minimum_withdrawal_amount }}
                                    </div>

                                </div>

                            </div>

                        </div>


                        <div class="card-footer text-end">

                            <button
                                type="submit"
                                class="btn btn-warning"
                                :disabled="marketplaceForm.processing"
                            >
                <span v-if="marketplaceForm.processing">
                    Saving...
                </span>

                                <span v-else>
                    <i class="bi bi-check-lg me-1"></i>
                    Save Marketplace Settings
                </span>
                            </button>

                        </div>

                    </div>
                </form>



<!--                <form @submit.prevent="submit">-->
                <form
                    v-if="activeSetting === 'logo'"
                    @submit.prevent="submitLogoSettings"
                >

                    <div class="card">

                        <div class="card-header">

                            <div class="d-flex align-items-center gap-2">

                                <i class="bi bi-image"></i>

                                <strong>
                                    Logo Settings
                                </strong>

                            </div>

                        </div>


                        <div class="card-body">

                            <div class="row g-4">

                                <!-- LOGO -->
                                <div class="col-md-6">

                                    <label
                                        class="form-label fw-semibold"
                                    >
                                        Website Logo
                                    </label>

                                    <div
                                        class="image-preview-box mb-3"
                                    >

                                        <img
                                            v-if="logoPreview"
                                            :src="logoPreview"
                                            alt="Logo Preview"
                                        />

                                        <img
                                            v-else-if="settings.site_logo"
                                            :src="
                                                `${appUrl('/storage')}/${settings.site_logo}`
                                            "
                                            alt="Website Logo"
                                        />

                                        <div
                                            v-else
                                            class="empty-preview"
                                        >
                                            <i
                                                class="bi bi-image fs-1"
                                            ></i>

                                            <span>
                                                No logo uploaded
                                            </span>
                                        </div>

                                    </div>

                                    <input
                                        type="file"
                                        class="form-control"
                                        accept=".jpg,.jpeg,.png,.webp,.svg"
                                        @change="selectLogo"
                                    />

                                    <small
                                        class="form-text"
                                    >
                                        Recommended PNG, WEBP or SVG.
                                        Maximum 4 MB.
                                    </small>

                                    <div
                                        v-if="form.errors.logo"
                                        class="text-danger small mt-1"
                                    >
                                        {{ form.errors.logo }}
                                    </div>

                                </div>


                                <!-- FAVICON -->
                                <div class="col-md-6">

                                    <label
                                        class="form-label fw-semibold"
                                    >
                                        Website Favicon
                                    </label>

                                    <div
                                        class="image-preview-box favicon-preview mb-3"
                                    >

                                        <img
                                            v-if="faviconPreview"
                                            :src="faviconPreview"
                                            alt="Favicon Preview"
                                        />

                                        <img
                                            v-else-if="settings.site_favicon"
                                            :src="
                                                `${appUrl('/storage')}/${settings.site_favicon}`
                                            "
                                            alt="Website Favicon"
                                        />

                                        <div
                                            v-else
                                            class="empty-preview"
                                        >

                                            <i
                                                class="bi bi-browser-chrome fs-1"
                                            ></i>

                                            <span>
                                                No favicon uploaded
                                            </span>

                                        </div>

                                    </div>

                                    <input
                                        type="file"
                                        class="form-control"
                                        accept=".ico,.png,.jpg,.jpeg,.webp,.svg"
                                        @change="selectFavicon"
                                    />

                                    <small
                                        class="form-text"
                                    >
                                        Recommended 32×32 or 64×64 PNG/ICO.
                                        Maximum 2 MB.
                                    </small>

                                    <div
                                        v-if="form.errors.favicon"
                                        class="text-danger small mt-1"
                                    >
                                        {{ form.errors.favicon }}
                                    </div>

                                </div>

                            </div>


                            <hr class="my-4">


                            <button
                                type="submit"
                                class="btn btn-warning"
                                :disabled="form.processing"
                            >

                                <span
                                    v-if="form.processing"
                                >
                                    Saving...
                                </span>

                                <span v-else>
                                    <i class="bi bi-check-lg me-1"></i>

                                    Save Logo Settings
                                </span>

                            </button>

                        </div>

                    </div>

                </form>

            </div>

        </div>

    </AdminLayout>
</template>


<style scoped>
.settings-menu {
    position: sticky;
    top: 20px;
}

.settings-menu-item {
    display: flex;
    align-items: center;
    gap: .75rem;

    padding: .8rem .9rem;

    margin-bottom: .2rem;

    border-radius: .6rem;

    color: #cbd5e1;

    font-size: .9rem;

    transition:
        background-color .2s ease,
        color .2s ease;
}

.settings-menu-item i {
    width: 20px;

    color: #94a3b8;

    text-align: center;
}

.settings-menu-item.active {
    background: #26344c;

    color: #ffffff;
}

.settings-menu-item.active i {
    color: #fbbf24;
}

.settings-menu-item.disabled {
    cursor: not-allowed;

    opacity: .55;
}

.image-preview-box {
    display: flex;

    height: 170px;

    align-items: center;

    justify-content: center;

    overflow: hidden;

    padding: 1rem;

    border: 1px dashed #475569;

    border-radius: .75rem;

    background: #162235;
}

.image-preview-box img {
    max-width: 100%;

    max-height: 130px;

    object-fit: contain;
}

.image-preview-box.favicon-preview img {
    max-width: 80px;

    max-height: 80px;
}

.empty-preview {
    display: flex;

    flex-direction: column;

    align-items: center;

    gap: .4rem;

    color: #94a3b8;
}

/* navigation item css*/
.settings-menu-item {
    display: flex;
    align-items: center;
    gap: .75rem;
    padding: .8rem .9rem;
    margin-bottom: .2rem;
    border-radius: .6rem;
    transition: all .2s ease;
}

.settings-menu-item:not(.disabled) {
    cursor: pointer;
}

.settings-menu-item:not(.disabled):hover {
    background: #26344c;
}

.settings-menu-item.active {
    background: #26344c;
    color: #ffffff;
}

.settings-menu-item.disabled {
    cursor: not-allowed;
    opacity: .5;
}

</style>
