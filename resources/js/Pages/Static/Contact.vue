<script setup>
import { computed, ref } from 'vue';
import { useForm, usePage } from '@inertiajs/vue3';
import AppLayout from '../../Layouts/AppLayout.vue';
import EmptyState from '../../Components/Public/States/EmptyState.vue';
import PublicPageHero from '../../Components/Public/Content/PublicPageHero.vue';
import SeoHead from '../../Components/SeoHead.vue';
import { appUrl } from '../../appUrl';

const page = usePage();
const settings = computed(() => page.props.siteSettings ?? {});
const submitted = ref(false);

const form = useForm({
    enquiry_type: 'quick',
    full_name: '',
    phone: '',
});

function submit() {
    submitted.value = false;
    form.post(appUrl('/enquiries'), {
        preserveScroll: true,
        onSuccess: () => {
            submitted.value = true;
            form.reset('full_name', 'phone');
        },
    });
}
</script>

<template>
    <AppLayout>
        <SeoHead
            title="Contact"
            description="Contact the travel marketplace team for help planning your next journey."
            :canonical="appUrl('/contact')"
        />
        <PublicPageHero
            eyebrow="Get in touch"
            title="Let’s plan the next step."
            description="Use the enquiry form for a callback, or choose a configured contact channel below."
        />

        <main class="public-container py-5">
            <div class="row g-4 g-xl-5">
                <div class="col-lg-7">
                    <section class="public-content-panel h-100">
                        <p class="public-eyebrow">Enquiry</p>
                        <h2 class="public-heading public-heading--2">Tell us how to reach you.</h2>
                        <p class="text-muted">Share your name and phone number. The marketplace team will follow up using the contact details you provide.</p>

                        <div v-if="submitted" class="alert alert-success" role="status">Thank you. Your enquiry has been received.</div>

                        <form class="mt-4" @submit.prevent="submit">
                            <div class="mb-3">
                                <label for="contact-name" class="form-label">Name</label>
                                <input id="contact-name" v-model="form.full_name" class="form-control" required maxlength="255" autocomplete="name">
                                <div v-if="form.errors.full_name" class="text-danger small mt-1">{{ form.errors.full_name }}</div>
                            </div>
                            <div class="mb-3">
                                <label for="contact-phone" class="form-label">Phone</label>
                                <input id="contact-phone" v-model="form.phone" class="form-control" type="tel" required maxlength="20" autocomplete="tel">
                                <div v-if="form.errors.phone" class="text-danger small mt-1">{{ form.errors.phone }}</div>
                            </div>
                            <button type="submit" class="public-button public-button--primary" :disabled="form.processing">
                                {{ form.processing ? 'Sending…' : 'Send enquiry' }}
                            </button>
                        </form>
                    </section>
                </div>

                <div class="col-lg-5">
                    <section v-if="settings.contact_email || settings.primary_phone || settings.secondary_phone || settings.office_address || settings.google_maps_url" class="public-content-panel h-100">
                        <p class="public-eyebrow">Contact details</p>
                        <h2 class="public-heading public-heading--3">Choose a configured channel.</h2>
                        <dl class="mb-0">
                            <div v-if="settings.contact_email" class="contact-detail">
                                <dt>Email</dt>
                                <dd><a :href="'mailto:' + settings.contact_email">{{ settings.contact_email }}</a></dd>
                            </div>
                            <div v-if="settings.primary_phone" class="contact-detail">
                                <dt>Phone</dt>
                                <dd><a :href="'tel:' + settings.primary_phone.replace(/[^\d+]/g, '')">{{ settings.primary_phone }}</a></dd>
                            </div>
                            <div v-if="settings.secondary_phone" class="contact-detail">
                                <dt>Additional phone</dt>
                                <dd><a :href="'tel:' + settings.secondary_phone.replace(/[^\d+]/g, '')">{{ settings.secondary_phone }}</a></dd>
                            </div>
                            <div v-if="settings.office_address" class="contact-detail">
                                <dt>Office</dt>
                                <dd>{{ settings.office_address }}</dd>
                            </div>
                        </dl>
                        <a v-if="settings.google_maps_url" :href="settings.google_maps_url" target="_blank" rel="noopener" class="public-button public-button--outline mt-3">Open location</a>
                    </section>
                    <EmptyState v-else title="Contact details are being configured" description="The enquiry form is available while optional contact channels are prepared.">
                        <template #icon><i class="bi bi-chat-left-text" aria-hidden="true"></i></template>
                    </EmptyState>
                </div>
            </div>
        </main>
    </AppLayout>
</template>

<style scoped>
.public-content-panel {
    padding: clamp(1.5rem, 4vw, 2.5rem);
    border: 1px solid rgba(31, 72, 67, 0.1);
    border-radius: 1.25rem;
    background: #fffdf8;
    box-shadow: 0 1rem 2.5rem rgba(31, 72, 67, 0.07);
}

.contact-detail {
    padding: 1rem 0;
    border-bottom: 1px solid rgba(31, 72, 67, 0.1);
}

.contact-detail:last-child {
    border-bottom: 0;
}

.contact-detail dt {
    margin-bottom: 0.25rem;
    color: var(--public-muted, #65716d);
    font-size: 0.78rem;
    letter-spacing: 0.08em;
    text-transform: uppercase;
}

.contact-detail dd {
    margin: 0;
}
</style>
