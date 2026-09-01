<script setup>
import { appUrl } from '../appUrl';
import { Link } from '@inertiajs/vue3';
</script>

<template>
    <div class="d-flex">
        <aside class="bg-dark text-white p-3" style="width: 240px; min-height: 100vh;">
            <h5 class="mb-4">SVTP Admin</h5>
            <nav class="d-flex flex-column gap-2">
                <Link :href="appUrl('/admin')" class="text-white text-decoration-none">Dashboard</Link>
                <Link :href="appUrl('/admin/packages')" class="text-white text-decoration-none">Packages</Link>
                <Link :href="appUrl('/admin/banners')" class="text-white text-decoration-none">Banners</Link>
                <Link :href="appUrl('/admin/bookings')" class="text-white text-decoration-none">Bookings</Link>
                <Link :href="appUrl('/admin/reviews')" class="text-white text-decoration-none">Reviews</Link>
            </nav>
        </aside>
        <main class="flex-grow-1 p-4">
            <slot />
        </main>
    </div>
</template>
