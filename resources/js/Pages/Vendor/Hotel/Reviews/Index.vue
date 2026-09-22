<script setup>
import VendorLayout from '../../../../Layouts/VendorLayout.vue';
import ReviewManagementList from '../../../../Components/Hotel/ReviewManagementList.vue';
defineProps({ reviews: Object, filters: Object, properties: Array, statuses: Array });
</script>

<template>
    <VendorLayout><ReviewManagementList :reviews="reviews" :filters="filters" :properties="properties" :statuses="statuses" base-path="/vendor/hotel/reviews" /></VendorLayout>
</template>
