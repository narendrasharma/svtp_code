<script setup>
import { computed, ref } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import QuickEnquiryModal from '../Components/QuickEnquiryModal.vue';
import DevotionalDivider from '../Components/DevotionalDivider.vue';
import FooterReviewPlatforms from '../Components/FooterReviewPlatforms.vue';
import TourPlanEnquiryModal from '../Components/TourPlanEnquiryModal.vue';
import WhatsAppFloat from '../Components/WhatsAppFloatButton.vue';
import TrustBadgeMarquee from '../Components/TrustBadgeMarquee.vue';
import TopBar from '../Components/TopBar.vue';
import Logo from '../Components/Logo.vue';
import GlobalSearch from '../Components/GlobalSearch.vue';
import PromotionalPopupModal from '../Components/PromotionalPopupModal.vue';
import { appUrl } from '../appUrl';
import { contactInfo } from '../festiveAssets';

const page = usePage();
const year = new Date().getFullYear();
const isQuickEnquiryOpen = ref(false);
const isTourEnquiryOpen = ref(false);
const isMobileMenuOpen = ref(false);
const isMobileCategoriesOpen = ref(false);
const tourCategories = computed(() => page.props.tourCategories || []);
const currentPath = computed(() => page.url.split('?')[0]);
const hasCategoryFilter = computed(() => new URLSearchParams(page.url.split('?')[1] || '').has('category'));

function isActiveSection(section) {
    const path = currentPath.value;

    if (section === 'packages') {
        return path.startsWith('/packages') && !(path === '/packages' && hasCategoryFilter.value);
    }

    if (section === 'tour-categories') {
        return path === '/packages' && hasCategoryFilter.value;
    }

    if (section === 'destinations') {
        return path.startsWith('/destinations') || path.startsWith('/places/');
    }

    return path === `/${section}` || path.startsWith(`/${section}/`);
}

function closeMobileMenu() {
    isMobileMenuOpen.value = false;
    isMobileCategoriesOpen.value = false;
}
</script>

<template>
    <div class="d-flex flex-column min-vh-100">
        <TopBar />
        <nav class="svtp-navbar">
            <div class="container navbar-shell">
                <Link :href="appUrl('/')" class="navbar-brand mb-0"><Logo /></Link>
                <div class="d-flex min-width-0 align-items-center">
                    <div class="d-none d-xl-flex align-items-center">
                        <Link :href="appUrl('/packages')" class="nav-link-custom" :class="{ 'is-active': isActiveSection('packages') }" :aria-current="isActiveSection('packages') ? 'page' : undefined">Packages</Link>
                        <div v-if="tourCategories.length" class="dropdown">
                            <button class="nav-link-custom border-0 bg-transparent dropdown-toggle" :class="{ 'is-active': isActiveSection('tour-categories') }" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                                <span>Tour Categories</span>
                                <i class="bi bi-chevron-down nav-chevron" aria-hidden="true"></i>
                            </button>
                            <ul class="dropdown-menu shadow border-0">
                                <li v-for="category in tourCategories" :key="category.id">
                                    <Link :href="appUrl(`/packages?category=${category.slug}`)" class="dropdown-item">
                                        <i class="bi me-2" :class="category.icon || 'bi-map'"></i>{{ category.name }}
                                    </Link>
                                </li>
                            </ul>
                        </div>
                        <Link :href="appUrl('/destinations')" class="nav-link-custom" :class="{ 'is-active': isActiveSection('destinations') }" :aria-current="isActiveSection('destinations') ? 'page' : undefined">Destinations</Link>
                        <Link :href="appUrl('/spiritual-wisdom')" class="nav-link-custom" :class="{ 'is-active': isActiveSection('spiritual-wisdom') }" :aria-current="isActiveSection('spiritual-wisdom') ? 'page' : undefined">Wisdom</Link>
                        <Link :href="appUrl('/gallery')" class="nav-link-custom" :class="{ 'is-active': isActiveSection('gallery') }" :aria-current="isActiveSection('gallery') ? 'page' : undefined">Gallery</Link>
                        <Link :href="appUrl('/blog')" class="nav-link-custom" :class="{ 'is-active': isActiveSection('blog') }" :aria-current="isActiveSection('blog') ? 'page' : undefined">Blog</Link>
                        <Link :href="appUrl('/about')" class="nav-link-custom" :class="{ 'is-active': isActiveSection('about') }" :aria-current="isActiveSection('about') ? 'page' : undefined">About</Link>
                        <Link :href="appUrl('/contact')" class="nav-link-custom" :class="{ 'is-active': isActiveSection('contact') }" :aria-current="isActiveSection('contact') ? 'page' : undefined">Contact</Link>
                    </div>
                    <GlobalSearch />
                    <button
                        type="button"
                        class="mobile-menu-toggle d-xl-none ms-2"
                        aria-label="Toggle navigation menu"
                        aria-controls="mobile-navigation"
                        :aria-expanded="isMobileMenuOpen"
                        @click="isMobileMenuOpen = !isMobileMenuOpen"
                    >
                        <i class="bi" :class="isMobileMenuOpen ? 'bi-x-lg' : 'bi-list'" aria-hidden="true"></i>
                    </button>
                    <button type="button" class="btn btn-svtp ms-2 d-none d-xl-inline-flex" @click="isTourEnquiryOpen = true">Enquiry</button>
                </div>

                <div v-if="isMobileMenuOpen" id="mobile-navigation" class="mobile-navigation d-xl-none">
                    <Link :href="appUrl('/packages')" class="mobile-nav-link" @click="closeMobileMenu">Packages</Link>
                    <div v-if="tourCategories.length" class="mobile-category-menu">
                        <button
                            type="button"
                            class="mobile-nav-link mobile-category-toggle"
                            :aria-expanded="isMobileCategoriesOpen"
                            aria-controls="mobile-tour-categories"
                            @click="isMobileCategoriesOpen = !isMobileCategoriesOpen"
                        >
                            <span>Tour Categories</span>
                            <i class="bi" :class="isMobileCategoriesOpen ? 'bi-chevron-up' : 'bi-chevron-down'" aria-hidden="true"></i>
                        </button>
                        <div v-if="isMobileCategoriesOpen" id="mobile-tour-categories" class="mobile-category-links">
                            <Link
                                v-for="category in tourCategories"
                                :key="category.id"
                                :href="appUrl(`/packages?category=${category.slug}`)"
                                @click="closeMobileMenu"
                            >
                                <i class="bi me-2" :class="category.icon || 'bi-map'" aria-hidden="true"></i>{{ category.name }}
                            </Link>
                        </div>
                    </div>
                    <Link :href="appUrl('/destinations')" class="mobile-nav-link" @click="closeMobileMenu">Destinations</Link>
                    <Link :href="appUrl('/spiritual-wisdom')" class="mobile-nav-link" @click="closeMobileMenu">Wisdom</Link>
                    <Link :href="appUrl('/gallery')" class="mobile-nav-link" @click="closeMobileMenu">Gallery</Link>
                    <Link :href="appUrl('/blog')" class="mobile-nav-link" @click="closeMobileMenu">Blog</Link>
                    <Link :href="appUrl('/about')" class="mobile-nav-link" @click="closeMobileMenu">About</Link>
                    <Link :href="appUrl('/contact')" class="mobile-nav-link" @click="closeMobileMenu">Contact</Link>
                    <button type="button" class="btn btn-svtp mobile-enquiry-button" @click="closeMobileMenu(); isTourEnquiryOpen = true">Enquiry</button>
                </div>
            </div>
        </nav>

        <div v-if="page.props.flash?.message" class="alert alert-success text-center mb-0 rounded-0">
            {{ page.props.flash.message }}
        </div>

        <main class="flex-grow-1">
            <slot />
        </main>

        <DevotionalDivider />
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
                            <li v-for="category in tourCategories" :key="category.id" class="mb-2"><Link :href="appUrl(`/packages?category=${category.slug}`)">{{ category.name }}</Link></li>
                            <li class="mb-2"><Link :href="appUrl('/gallery')">Gallery</Link></li>
                            <li class="mb-2"><Link :href="appUrl('/blog')">Blog</Link></li>
                            <li class="mb-2"><Link :href="appUrl('/about')">About Us</Link></li>
                            <li class="mb-2"><Link :href="appUrl('/our-team')">Our Team</Link></li>
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

            <FooterReviewPlatforms />

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
        <PromotionalPopupModal :popup="page.props.promotionalPopup" />
        <QuickEnquiryModal :open="isQuickEnquiryOpen" @close="isQuickEnquiryOpen = false" />
        <TourPlanEnquiryModal
            :open="isTourEnquiryOpen"
            :security-question="page.props.securityQuestion"
            @close="isTourEnquiryOpen = false"
        />
    </div>
</template>

<style scoped>
.navbar-shell {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    justify-content: space-between;
}

.min-width-0 {
    min-width: 0;
}

.mobile-menu-toggle {
    display: grid;
    width: 2.5rem;
    height: 2.5rem;
    flex: 0 0 2.5rem;
    place-items: center;
    border: 1px solid rgba(107, 16, 41, 0.16);
    border-radius: 50%;
    background: #fff;
    color: var(--maroon);
    font-size: 1.3rem;
}

.mobile-navigation {
    width: 100%;
    max-height: calc(100dvh - 5.5rem);
    overflow-x: hidden;
    overflow-y: auto;
    overscroll-behavior: contain;
    padding: 0.75rem 0 0.25rem;
    scrollbar-gutter: stable;
}

.mobile-nav-link {
    display: flex;
    width: 100%;
    align-items: center;
    justify-content: space-between;
    padding: 0.7rem 0.25rem;
    border: 0;
    border-bottom: 1px solid rgba(107, 16, 41, 0.09);
    background: transparent;
    color: var(--ink);
    text-align: left;
    text-decoration: none;
}

.mobile-category-links {
    display: grid;
    padding: 0.25rem 0 0.45rem 0.75rem;
}

.mobile-category-links a {
    min-width: 0;
    padding: 0.55rem 0.5rem;
    color: var(--maroon);
    overflow-wrap: anywhere;
    text-decoration: none;
}

.mobile-enquiry-button {
    width: 100%;
    margin-top: 0.8rem;
}

@media (max-width: 575.98px) {
    .svtp-navbar {
        padding: 0.65rem 0;
    }

    .navbar-brand {
        min-width: 0;
        max-width: calc(100% - 6.25rem);
    }

    .navbar-brand :deep(.svtp-logo-image) {
        width: min(100%, 190px);
    }
}
</style>
