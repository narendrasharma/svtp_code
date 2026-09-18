<script setup>
import { appUrl } from '../../appUrl';
import { blogPosts } from '../../festiveAssets';

defineProps({
    settings: { type: Object, default: () => ({}) },
});
</script>

<template>
    <section class="container py-5">
        <p class="section-eyebrow text-center">यात्रा गाइड</p>
        <h2 class="section-title text-center mb-5">{{ settings.heading || 'From the Travel Blog' }}</h2>
        <div class="row g-4">
            <div v-for="post in blogPosts" :key="post.title" class="col-md-4">
                <div class="blog-card">
                    <img :src="post.image" :alt="post.title" loading="lazy" />
                    <div class="blog-card-body">
                        <h6 class="text-svtp">{{ post.title }}</h6>
                        <p class="small text-muted">{{ post.excerpt }}</p>
                        <small class="text-muted fst-italic">{{ post.date }}</small>
                    </div>
                </div>
            </div>
        </div>
        <div class="text-center mt-4">
            <a :href="appUrl('/blog')" class="btn btn-outline-svtp">Read All Guides</a>
        </div>
    </section>
</template>
