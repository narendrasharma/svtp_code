<script setup>
import AppLayout from '../../Layouts/AppLayout.vue';
import SeoHead from "@/Components/SeoHead.vue";

const faqs = [
    { q: 'How do I book a tour?', a: 'Choose a package, select your travel date and number of travelers, then pay securely via Razorpay or Paytm.' },
    { q: 'Is there a WhatsApp booking option?', a: 'Yes — tap the WhatsApp icon on any page to chat with our team directly.' },
    { q: 'Can I customize a package?', a: 'Yes, contact us and we will tailor an itinerary to your needs.' },
    { q: 'How do I get my booking confirmation?', a: 'You will receive a QR pass and downloadable PDF invoice right after payment.' },
];
</script>

<template>
    <AppLayout>
        <SeoHead
            title="Frequently Asked Questions"
            description="Find answers about tour bookings, payments, cancellations, travel arrangements and our services."
        />
        <div class="container py-5" style="max-width: 720px;">
            <h1 class="brand-heading mb-4">Frequently Asked Questions</h1>
            <div class="accordion" id="faqAccordion">
                <div v-for="(item, i) in faqs" :key="i" class="accordion-item">
                    <h2 class="accordion-header">
                        <button class="accordion-button" :class="{ collapsed: i !== 0 }" type="button" data-bs-toggle="collapse" :data-bs-target="`#faq-${i}`">
                            {{ item.q }}
                        </button>
                    </h2>
                    <div :id="`faq-${i}`" class="accordion-collapse collapse" :class="{ show: i === 0 }" data-bs-parent="#faqAccordion">
                        <div class="accordion-body">{{ item.a }}</div>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
