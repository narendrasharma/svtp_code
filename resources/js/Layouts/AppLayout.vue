<script setup>
import { ref } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import QuickEnquiryModal from '../Components/QuickEnquiryModal.vue';
import TourPlanEnquiryModal from '../Components/TourPlanEnquiryModal.vue';
import WhatsAppFloat from '../Components/WhatsAppFloatButton.vue';
import TrustBadgeMarquee from '../Components/TrustBadgeMarquee.vue';
import TopBar from '../Components/TopBar.vue';
import Logo from '../Components/Logo.vue';
import { appUrl } from '../appUrl';
import { contactInfo } from '../festiveAssets';

const page = usePage();
const year = new Date().getFullYear();
const isQuickEnquiryOpen = ref(false);
const isTourEnquiryOpen = ref(false);
</script>

<template>
    <div class="d-flex flex-column min-vh-100">
        <TopBar />
        <nav class="svtp-navbar">
            <div class="container d-flex align-items-center justify-content-between">
                <Link :href="appUrl('/')" class="navbar-brand mb-0"><Logo /></Link>
                <div class="d-none d-md-flex align-items-center">
                    <Link :href="appUrl('/packages')" class="nav-link-custom">Packages</Link>
                    <Link :href="appUrl('/destinations')" class="nav-link-custom">Destinations</Link>
                    <Link :href="appUrl('/spiritual-wisdom')" class="nav-link-custom">Wisdom</Link>
                    <Link :href="appUrl('/gallery')" class="nav-link-custom">Gallery</Link>
                    <Link :href="appUrl('/blog')" class="nav-link-custom">Blog</Link>
                    <Link :href="appUrl('/about')" class="nav-link-custom">About</Link>
                    <Link :href="appUrl('/contact')" class="nav-link-custom">Contact</Link>
                    <button type="button" class="btn btn-svtp ms-3" @click="isTourEnquiryOpen = true">Enquiry</button>
                </div>
            </div>
        </nav>

        <div v-if="page.props.flash?.message" class="alert alert-success text-center mb-0 rounded-0">
            {{ page.props.flash.message }}
        </div>

        <main class="flex-grow-1">
            <slot />
        </main>

        <footer class="site-footer pt-5">
            <div class="container pb-4">
                <div class="row g-4">
                    <div class="col-lg-4 col-md-6">
                        <p class="devanagari mb-2" style="color: var(--gold-soft); font-size: 1.15rem;">॥ राधे राधे ॥</p>
                        <h6>Shree Vrindavan Tour Packages</h6>
                        <p class="small opacity-75 mb-3">
                            Guided pilgrimages across Braj Bhoomi — Vrindavan, Mathura, Gokul, Barsana and Govardhan —
                            with transparent pricing and real online booking.
                        </p>
                        <div class="d-flex gap-2">
                            <a href="#" class="footer-social" aria-label="Facebook"><i class="bi bi-facebook"></i></a>
                            <a href="#" class="footer-social" aria-label="Instagram"><i class="bi bi-instagram"></i></a>
                            <a href="#" class="footer-social" aria-label="YouTube"><i class="bi bi-youtube"></i></a>
                            <a href="https://wa.me/918923427393" target="_blank" rel="noopener" class="footer-social" aria-label="WhatsApp"><i class="bi bi-whatsapp"></i></a>
                        </div>
                    </div>

                    <div class="col-lg-2 col-md-6">
                        <h6>Explore</h6>
                        <ul class="list-unstyled small">
                            <li class="mb-2"><Link :href="appUrl('/packages')">All Tour Packages</Link></li>
                            <li class="mb-2"><Link :href="appUrl('/destinations')">Explore Braj</Link></li>
                            <li class="mb-2"><Link :href="appUrl('/packages?category=festival')">Holi Specials</Link></li>
                            <li class="mb-2"><Link :href="appUrl('/gallery')">Gallery</Link></li>
                            <li class="mb-2"><Link :href="appUrl('/blog')">Blog</Link></li>
                            <li class="mb-2"><Link :href="appUrl('/about')">About Us</Link></li>
                        </ul>
                    </div>

                    <div class="col-lg-3 col-md-6">
                        <h6>Support</h6>
                        <ul class="list-unstyled small">
                            <li class="mb-2"><Link :href="appUrl('/faq')">FAQs</Link></li>
                            <li class="mb-2"><Link :href="appUrl('/testimonials')">Testimonials</Link></li>
                            <li class="mb-2"><Link :href="appUrl('/contact')">Contact Us</Link></li>
                            <li class="mb-2"><Link :href="appUrl('/terms')">Terms &amp; Conditions</Link></li>
                            <li class="mb-2"><Link :href="appUrl('/privacy')">Privacy Policy</Link></li>
                        </ul>
                    </div>

                    <div class="col-lg-3 col-md-6">
                        <h6>Reach Us</h6>
                        <p class="small mb-2"><i class="bi bi-geo-alt-fill me-2"></i><a :href="contactInfo.mapUrl" target="_blank" rel="noopener">{{ contactInfo.headOffice }}</a></p>
                        <p class="small mb-2"><i class="bi bi-telephone-fill me-2"></i><a :href="contactInfo.phoneHref">{{ contactInfo.phone }}</a></p>
                        <p class="small mb-2"><i class="bi bi-envelope-fill me-2"></i><a :href="`mailto:${contactInfo.email}`">{{ contactInfo.email }}</a></p>
                        <p class="small mb-0"><i class="bi bi-whatsapp me-2"></i><a href="https://wa.me/918923427393" target="_blank" rel="noopener">Chat with us</a></p>
                    </div>
                </div>
            </div>

            <div class="py-3" style="border-top: 1px solid rgba(228,205,140,0.2); border-bottom: 1px solid rgba(228,205,140,0.2);">
                <TrustBadgeMarquee />
            </div>

            <div class="container py-3 text-center small opacity-75">
                © {{ year }} Shree Vrindavan Tour Packages. All rights reserved.
            </div>
        </footer>

        <button type="button" class="quick-enquiry-float" @click="isQuickEnquiryOpen = true">
            <i class="bi bi-chat-dots-fill"></i>
            <span>Quick Enquiry</span>
        </button>
        <WhatsAppFloat />
        <QuickEnquiryModal :open="isQuickEnquiryOpen" @close="isQuickEnquiryOpen = false" />
        <TourPlanEnquiryModal
            :open="isTourEnquiryOpen"
            :security-question="page.props.securityQuestion"
            @close="isTourEnquiryOpen = false"
        />
    </div>
</template>
