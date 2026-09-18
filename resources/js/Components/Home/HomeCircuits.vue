<script setup>
const circuits = [
    { name: 'Braj — Vrindavan & Mathura', note: 'Gokul, Nandgaon, Barsana, Govardhan', icon: 'bi-flower3' },
    { name: 'Uttar Pradesh Circuit', note: 'Ayodhya, Varanasi, Prayagraj', icon: 'bi-bank2' },
    { name: 'Rajasthan Circuit', note: 'Khatu Shyam Ji, Salasar Balaji', icon: 'bi-building' },
    { name: 'Uttarakhand Circuit', note: 'Haridwar, Rishikesh', icon: 'bi-water' },
];
</script>

<template>
    <section class="container py-5 mt-4">
        <div class="d-flex flex-wrap gap-3 justify-content-center">
            <div v-for="(c, i) in circuits" :key="c.name" class="category-chip fade-in-up" :class="`delay-${i + 1}`">
                <i class="bi" :class="c.icon"></i>
                <strong>{{ c.name }}</strong>
                <span class="text-muted">— {{ c.note }}</span>
            </div>
        </div>
    </section>
</template>
