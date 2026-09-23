<script setup>
import CardShell from '../Cards/CardShell.vue';
import Skeleton from './Skeleton.vue';
</script>

<template>
    <CardShell>
        <Skeleton variant="media" />
        <div class="p-3 p-md-4 d-grid gap-2">
            <Skeleton style="width: 35%" />
            <Skeleton style="width: 80%" />
            <Skeleton style="width: 55%" />
        </div>
    </CardShell>
</template>
