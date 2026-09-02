<script setup>
import dividerUrl from '../../images/braj-divider.svg';
</script>

<template>
    <div class="devotional-divider" aria-hidden="true">
        <img :src="dividerUrl" alt="">
    </div>
</template>

<style scoped>
.devotional-divider {
    position: relative;
    z-index: 2;
    width: 100%;
    height: clamp(54px, 6vw, 82px);
    overflow: hidden;
    background: #f9ddb9;
}

.devotional-divider img {
    display: block;
    width: 100%;
    height: 100%;
    object-fit: cover;
    object-position: center;
}
</style>
