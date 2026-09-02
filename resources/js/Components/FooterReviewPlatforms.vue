<script setup>
import { contactInfo } from '../festiveAssets';
</script>

<template>
    <section class="review-platforms" aria-labelledby="review-platforms-title">
        <div class="container review-platforms-inner">
            <p id="review-platforms-title" class="review-platforms-label">Find our reviews</p>
            <div class="review-platform-list">
                <a :href="contactInfo.mapUrl" target="_blank" rel="noopener" class="review-platform review-platform-link" aria-label="Read our Google reviews">
                    <span class="google-wordmark" aria-hidden="true"><b>G</b><b>o</b><b>o</b><b>g</b><b>l</b><b>e</b></span>
                    <span class="platform-caption">Reviews</span>
                </a>

                <span class="review-platform" role="img" aria-label="Tripadvisor profile coming soon" title="Tripadvisor profile coming soon">
                    <svg class="tripadvisor-mark" viewBox="0 0 42 26" aria-hidden="true">
                        <path d="M8 5L3 1M34 5L39 1M13 6C16 3 26 3 29 6" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                        <circle cx="11" cy="14" r="8" fill="none" stroke="currentColor" stroke-width="3"/>
                        <circle cx="31" cy="14" r="8" fill="none" stroke="currentColor" stroke-width="3"/>
                        <circle cx="11" cy="14" r="3" fill="#51B548"/>
                        <circle cx="31" cy="14" r="3" fill="#51B548"/>
                        <path d="M18 14L21 19L24 14" fill="#E65252"/>
                    </svg>
                    <span class="tripadvisor-wordmark">tripadvisor</span>
                </span>

                <span class="review-platform" role="img" aria-label="Trustpilot profile coming soon" title="Trustpilot profile coming soon">
                    <svg class="trustpilot-mark" viewBox="0 0 24 24" aria-hidden="true">
                        <path d="M12 1.8L15 8H22L16.5 12.4L18.6 19.1L12 15.2L5.4 19.1L7.5 12.4L2 8H9Z" fill="#00B67A"/>
                    </svg>
                    <span class="trustpilot-wordmark">Trustpilot</span>
                </span>
            </div>
        </div>
    </section>
</template>

<style scoped>
.review-platforms { border-top: 1px solid rgba(228, 205, 140, 0.18); }
.review-platforms-inner { display: flex; align-items: center; justify-content: center; gap: 1.4rem; padding-top: 1rem; padding-bottom: 1rem; }
.review-platforms-label { margin: 0; color: rgba(255, 248, 240, 0.68); font-size: 0.72rem; font-weight: 600; letter-spacing: 0.12em; text-transform: uppercase; }
.review-platform-list { display: flex; flex-wrap: wrap; align-items: center; justify-content: center; gap: 0.65rem; }
.review-platform {
    display: inline-flex;
    min-height: 38px;
    align-items: center;
    gap: 0.35rem;
    padding: 0.42rem 0.75rem;
    border: 1px solid rgba(228, 205, 140, 0.22);
    border-radius: 10px;
    color: #fff;
    background: rgba(255, 255, 255, 0.06);
}
.review-platform-link { text-decoration: none; transition: border-color 0.2s ease, background 0.2s ease, transform 0.2s ease; }
.review-platform-link:hover { border-color: rgba(228, 205, 140, 0.55); color: #fff; background: rgba(255, 255, 255, 0.11); transform: translateY(-1px); }
.google-wordmark { display: inline-flex; font-family: Arial, sans-serif; font-size: 1rem; letter-spacing: -0.08em; }
.google-wordmark b { font-weight: 600; }
.google-wordmark b:nth-child(1), .google-wordmark b:nth-child(4) { color: #4285f4; }
.google-wordmark b:nth-child(2), .google-wordmark b:nth-child(6) { color: #ea4335; }
.google-wordmark b:nth-child(3) { color: #fbbc05; }
.google-wordmark b:nth-child(5) { color: #34a853; }
.platform-caption { color: rgba(255, 255, 255, 0.72); font-size: 0.68rem; }
.tripadvisor-mark { width: 28px; color: #fff; }
.tripadvisor-wordmark, .trustpilot-wordmark { font-family: Arial, sans-serif; font-size: 0.84rem; font-weight: 700; letter-spacing: -0.025em; }
.trustpilot-mark { width: 19px; }

@media (max-width: 575px) {
    .review-platforms-inner { flex-direction: column; gap: 0.7rem; }
    .review-platform { padding-inline: 0.55rem; }
}
</style>
