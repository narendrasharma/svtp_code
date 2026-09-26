<script setup>
import AdminLayout from '../../../Layouts/AdminLayout.vue';
import { appUrl } from '../../../appUrl';
import { useForm } from '@inertiajs/vue3';
import { ref, watch } from 'vue';

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
        key: 'ai',
        label: 'AI Settings',
        icon: 'bi-stars',
        enabled: true,
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

const aiForm = useForm({
    ai_enabled: props.settings.ai?.enabled ?? false,
    ai_provider: props.settings.ai?.provider ?? 'openai',
    ai_model: props.settings.ai?.model ?? '',
    azure_endpoint: props.settings.ai?.azure_endpoint ?? '',
    azure_tool_calling: props.settings.ai?.azure_tool_calling ?? false,
    api_key: '',
    knowledge_enabled: props.settings.ai?.knowledge_enabled ?? false,
    agent_actions_enabled: props.settings.ai?.agent_actions_enabled ?? false,
    embedding_provider: props.settings.ai?.embedding_provider ?? 'openai',
    embedding_model: props.settings.ai?.embedding_model ?? 'text-embedding-3-small',
    embedding_api_key: '',
});

watch(() => aiForm.ai_provider, (provider) => {
    aiForm.ai_model = props.settings.ai?.default_models?.[provider] ?? '';
});

watch(() => aiForm.embedding_provider, (provider) => {
    aiForm.embedding_model = props.settings.ai?.embedding_models?.[provider]?.[0] ?? '';
    aiForm.embedding_api_key = '';
});

function submitAiSettings() {
    aiForm.post(appUrl('/admin/settings/ai'), {
        preserveScroll: true,
        onSuccess: () => aiForm.reset('api_key', 'embedding_api_key'),
    });
}


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
                            :class="{ active: activeSetting === item.key }"
                            @click="activeSetting = item.key"
                            >
                            <i
                            class="bi"
                            :class="item.icon"
                            ></i>

                            <span>
                            {{ item.label }}
                            </span>

                            </div>

                    </div>

                </div>

            </div>


            <!-- RIGHT CONTENT -->
            <div class="col-lg-9">


                <form v-if="activeSetting === 'ai'" @submit.prevent="submitAiSettings">
                    <div class="card">
                        <div class="card-header d-flex align-items-center gap-2">
                            <i class="bi bi-stars"></i>
                            <strong>AI Settings</strong>
                            <a :href="appUrl('/admin/mcp-access')" class="btn btn-sm btn-outline-secondary ms-auto">MCP Access</a>
                        </div>
                        <div class="card-body">
                            <p class="small text-muted mb-3">AI Provider is used for content generation and the Assistant. Knowledge Embedding Provider independently indexes and searches Knowledge for RAG.</p>
                            <div class="form-check form-switch mb-4">
                                <input id="ai-enabled" v-model="aiForm.ai_enabled" class="form-check-input" type="checkbox" />
                                <label class="form-check-label" for="ai-enabled">Enable AI features</label>
                            </div>
                            <hr class="my-4" />
                            <div class="form-check form-switch mb-3">
                                <input id="knowledge-enabled" v-model="aiForm.knowledge_enabled" class="form-check-input" type="checkbox" />
                                <label class="form-check-label" for="knowledge-enabled">Enable knowledge retrieval</label>
                            </div>
                            <div class="form-check form-switch mb-3">
                                <input id="agent-actions-enabled" v-model="aiForm.agent_actions_enabled" class="form-check-input" type="checkbox" />
                                <label class="form-check-label" for="agent-actions-enabled">Enable guarded Agent actions</label>
                                <div class="form-text">Every proposed change still requires confirmation in the Assistant.</div>
                            </div>
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label" for="embedding-provider">Knowledge embedding provider</label>
                                    <select id="embedding-provider" v-model="aiForm.embedding_provider" class="form-select">
                                        <option v-for="provider in settings.ai?.embedding_providers ?? []" :key="provider" :value="provider">{{ provider === 'openai' ? 'OpenAI' : 'Gemini' }}</option>
                                    </select>
                                    <div v-if="aiForm.errors.embedding_provider" class="text-danger small mt-1">{{ aiForm.errors.embedding_provider }}</div>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label" for="embedding-model">Embedding model</label>
                                    <select id="embedding-model" v-model="aiForm.embedding_model" class="form-select">
                                        <option v-for="model in settings.ai?.embedding_models?.[aiForm.embedding_provider] ?? []" :key="model" :value="model">{{ model }}</option>
                                    </select>
                                    <div v-if="aiForm.errors.embedding_model" class="text-danger small mt-1">{{ aiForm.errors.embedding_model }}</div>
                                </div>
                                <div v-if="aiForm.embedding_provider !== aiForm.ai_provider" class="col-12">
                                    <label class="form-label" for="embedding-api-key">{{ aiForm.embedding_provider === 'gemini' ? 'Gemini' : 'OpenAI' }} API key for Knowledge</label>
                                    <input id="embedding-api-key" v-model="aiForm.embedding_api_key" class="form-control" type="password" autocomplete="new-password" placeholder="Leave blank to keep the current key" />
                                    <div class="form-text">{{ settings.ai?.embedding_credentials?.[aiForm.embedding_provider] ? 'Configured. The saved key is never displayed.' : 'Enter your provider key to index and search Knowledge.' }}</div>
                                    <div v-if="aiForm.errors.embedding_api_key" class="text-danger small mt-1">{{ aiForm.errors.embedding_api_key }}</div>
                                </div>
                                <div v-else class="col-12 small text-muted">Knowledge uses the same encrypted {{ aiForm.embedding_provider === 'gemini' ? 'Gemini' : 'OpenAI' }} key as AI Provider. {{ settings.ai?.embedding_credentials?.[aiForm.embedding_provider] ? 'Configured.' : 'Enter it in the API key field below.' }}</div>
                                <div class="col-12 small text-muted">Changing the embedding provider or model requires reindexing existing Knowledge. Use Reindex in AI Knowledge after saving; no embeddings are regenerated by changing this setting.</div>
                            </div>
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label" for="ai-provider">Assistant / Content provider</label>
                                    <select id="ai-provider" v-model="aiForm.ai_provider" class="form-select">
                                        <option v-for="provider in settings.ai?.providers ?? []" :key="provider" :value="provider">
                                            {{ provider === 'openai' ? 'OpenAI' : provider === 'claude' ? 'Claude' : provider === 'azure' ? 'Azure Foundry' : 'Gemini' }}
                                        </option>
                                    </select>
                                    <div v-if="aiForm.errors.ai_provider" class="text-danger small mt-1">{{ aiForm.errors.ai_provider }}</div>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label" for="ai-model">{{ aiForm.ai_provider === 'azure' ? 'Deployment / model' : 'Default model' }}</label>
                                    <input id="ai-model" v-model="aiForm.ai_model" class="form-control" type="text" maxlength="100" placeholder="gpt-4o-mini" />
                                    <div v-if="aiForm.ai_provider === 'azure'" class="form-text">Enter your deployed model name, not the model family.</div>
                                    <div v-else class="form-text">For Gemini Assistant, use a supported function-calling model such as gemini-3.8-flash. Existing saved models stay unchanged.</div>
                                    <div v-if="aiForm.errors.ai_model" class="text-danger small mt-1">{{ aiForm.errors.ai_model }}</div>
                                </div>
                                <template v-if="aiForm.ai_provider === 'azure'">
                                    <div class="col-12">
                                        <label class="form-label" for="azure-endpoint">Endpoint / project endpoint</label>
                                        <input id="azure-endpoint" v-model="aiForm.azure_endpoint" class="form-control" type="url" maxlength="500" placeholder="https://your-resource.services.ai.azure.com/api/projects/your-project" />
                                        <div class="form-text">Paste the Azure OpenAI resource or Foundry project endpoint. Triparo uses its v1 Responses API.</div>
                                        <div v-if="aiForm.errors.azure_endpoint" class="text-danger small mt-1">{{ aiForm.errors.azure_endpoint }}</div>
                                    </div>
                                    <div class="col-12 form-check form-switch ms-2">
                                        <input id="azure-tool-calling" v-model="aiForm.azure_tool_calling" class="form-check-input" type="checkbox" />
                                        <label class="form-check-label" for="azure-tool-calling">This deployment supports function calling</label>
                                        <div class="form-text">Enable Assistant only after confirming function calling for this deployment. Content generation can work without it.</div>
                                    </div>
                                </template>
                                <div class="col-12">
                                    <label class="form-label" for="ai-api-key">API key</label>
                                    <input id="ai-api-key" v-model="aiForm.api_key" class="form-control" type="password" autocomplete="new-password" placeholder="Leave blank to keep the current key" />
                                    <div class="form-text">
                                        {{ settings.ai?.credentials?.[aiForm.ai_provider] ? 'Configured. Enter a new key only to replace it.' : 'Not configured. Enter your own provider key to enable drafts.' }}
                                    </div>
                                    <div v-if="aiForm.errors.api_key" class="text-danger small mt-1">{{ aiForm.errors.api_key }}</div>
                                </div>
                            </div>
                        </div>
                        <div class="card-footer text-end">
                            <button type="submit" class="btn btn-warning" :disabled="aiForm.processing">
                                {{ aiForm.processing ? 'Saving...' : 'Save AI Settings' }}
                            </button>
                        </div>
                    </div>
                </form>


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
