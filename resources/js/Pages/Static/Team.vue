<script setup>
import AppLayout from '../../Layouts/AppLayout.vue';
import { appUrl } from '../../appUrl';

const teamMembers = [
    { name: 'Kanhaiya Upadhyay', role: 'CEO', image: appUrl('/team/kanhaiya.jpeg') },
    { name: 'Narendra Sharma', role: 'Head – Tech & Digital Operations', image: appUrl('/team/narendra.jpeg') },
    { name: 'Abhishek Upadhyay', role: 'Travel Consultant', image: appUrl('/team/abhishek.jpeg') },
    { name: 'Ashok', role: 'Travel Consultant', image: appUrl('/team/ashok.jpeg') },
    { name: 'Suneel', role: 'Travel Consultant', image: appUrl('/team/Suneel.jpeg') },
    { name: 'Chaube', role: 'Travel Consultant', image: appUrl('/team/chaube.jpeg') },
];
</script>

<template>
    <AppLayout>
        <main class="team-page">
            <section class="team-hero">
                <div class="container text-center">
                    <p class="section-eyebrow">हमारी सेवा टोली</p>
                    <h1 class="brand-heading mb-3">Meet Our Braj Travel Team</h1>
                    <p class="team-intro mx-auto">Local people, heartfelt guidance, and practical experience for a peaceful journey through the sacred land of Radha and Krishna.</p>
                </div>
            </section>

            <section class="container team-grid-section">
                <div class="row g-4 justify-content-center">
                    <div v-for="member in teamMembers" :key="member.name" class="col-sm-6 col-lg-4">
                        <article class="team-card h-100">
                            <div class="team-photo-wrap">
                                <img v-if="member.image" :src="member.image" :alt="`${member.name}, ${member.role}`" class="team-photo">
                                <div v-else class="team-placeholder" role="img" :aria-label="`${member.name} photo placeholder`">
                                    <span class="placeholder-feather">❧</span>
                                    <strong>{{ member.initials }}</strong>
                                </div>
                                <span class="team-role">{{ member.role }}</span>
                            </div>
                            <div class="team-card-body">
                                <h2>{{ member.name }}</h2>
                                <p>{{ member.role === 'CEO' ? 'Leading every journey with local knowledge, care, and devotion to Braj.' : 'Helping guests plan comfortable, meaningful, and well-supported pilgrimages.' }}</p>
                            </div>
                        </article>
                    </div>
                </div>
            </section>
        </main>
    </AppLayout>
</template>

<style scoped>
.team-page { background: linear-gradient(180deg, #fffaf1 0%, #fff 34%, #fdf2f6 100%); }
.team-hero { position: relative; overflow: hidden; padding: clamp(4rem, 8vw, 6.5rem) 0 3rem; }
.team-hero::before, .team-hero::after { position: absolute; width: 20rem; aspect-ratio: 1; border-radius: 50%; content: ''; filter: blur(8px); opacity: 0.16; }
.team-hero::before { top: -11rem; left: -7rem; background: #2467a3; }
.team-hero::after { top: -10rem; right: -6rem; background: #e14d87; }
.team-intro { max-width: 670px; color: #6f6067; font-size: 1.05rem; }
.team-grid-section { padding-top: 1rem; padding-bottom: 5rem; }
.team-card { overflow: hidden; border: 1px solid rgba(122, 36, 93, 0.1); border-radius: 24px; background: #fff; box-shadow: 0 16px 38px rgba(74, 25, 54, 0.1); transition: transform 0.25s ease, box-shadow 0.25s ease; }
.team-card:hover { transform: translateY(-5px); box-shadow: 0 23px 48px rgba(74, 25, 54, 0.16); }
.team-photo-wrap { position: relative; height: 300px; overflow: hidden; background: #f8e8df; }
.team-photo { width: 100%; height: 100%; object-fit: cover; object-position: center 25%; transition: transform 0.45s ease; }
.team-card:hover .team-photo { transform: scale(1.035); }
.team-role { position: absolute; right: 1rem; bottom: 1rem; padding: 0.42rem 0.75rem; border: 1px solid rgba(255, 255, 255, 0.5); border-radius: 999px; color: #fff; background: rgba(83, 25, 64, 0.88); box-shadow: 0 5px 15px rgba(33, 12, 25, 0.2); backdrop-filter: blur(8px); font-size: 0.74rem; font-weight: 700; letter-spacing: 0.04em; }
.team-placeholder { display: grid; width: 100%; height: 100%; place-content: center; color: #fff8e8; background: radial-gradient(circle at 38% 30%, #3c7da1, #63305e 54%, #411633); text-align: center; }
.team-placeholder strong { font-family: var(--font-display); font-size: 4.5rem; font-weight: 600; line-height: 1; }
.placeholder-feather { margin-bottom: -0.4rem; color: #efb33e; font-size: 2.4rem; transform: rotate(-24deg); }
.team-card-body { padding: 1.35rem 1.4rem 1.5rem; text-align: center; }
.team-card-body h2 { margin-bottom: 0.45rem; color: var(--maroon); font-family: var(--font-display); font-size: 1.65rem; font-weight: 700; }
.team-card-body p { margin: 0; color: #75666e; font-size: 0.88rem; }

@media (max-width: 575px) {
    .team-photo-wrap { height: 340px; }
}

@media (prefers-reduced-motion: reduce) {
    .team-card, .team-photo { transition: none; }
}
</style>
