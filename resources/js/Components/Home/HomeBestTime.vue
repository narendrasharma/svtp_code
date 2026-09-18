<script setup>
import { bestTimeToVisit } from '../../festiveAssets';

defineProps({
    settings: { type: Object, default: () => ({}) },
});
</script>

<template>
    <section class="bg-cream-warm py-5">
        <div class="container">
            <p class="section-eyebrow text-center">यात्रा समय</p>
            <h2 class="section-title text-center mb-5">{{ settings.heading || 'Best Time to Visit' }}</h2>
            <div class="row justify-content-center">
                <div class="col-lg-9">
                    <div class="glass-card p-0 overflow-hidden">
                        <table class="table mb-0">
                            <thead>
                                <tr class="text-svtp">
                                    <th>Period</th><th>Weather</th><th>Best For</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="b in bestTimeToVisit" :key="b.period">
                                    <td class="fw-semibold">{{ b.period }}</td>
                                    <td>{{ b.weather }}</td>
                                    <td class="text-muted">{{ b.goodFor }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </section>
</template>
