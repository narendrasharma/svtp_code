<script setup>
import { Link } from '@inertiajs/vue3';

defineProps({
    links: Array
});
</script>

<template>
    <nav v-if="links?.length > 3" class="mt-4">
        <ul class="pagination justify-content-center">

            <li
                v-for="link in links"
                :key="link.label"
                class="page-item"
                :class="{
                    active: link.active,
                    disabled: !link.url
                }"
            >
                <Link
                    v-if="link.url"
                    :href="link.url"
                    class="page-link"
                    v-html="link.label"
                    preserve-scroll
                />

                <span
                    v-else
                    class="page-link"
                    v-html="link.label"
                ></span>
            </li>

        </ul>
    </nav>
</template>
