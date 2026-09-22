<script setup>
import AdminLayout from '../../../../Layouts/AdminLayout.vue';
import ReviewManagementList from '../../../../Components/Hotel/ReviewManagementList.vue';
defineProps({ reviews: Object, filters: Object, properties: Array, vendors: Array, statuses: Array });
</script>

<template>
    <AdminLayout><ReviewManagementList :reviews="reviews" :filters="filters" :properties="properties" :vendors="vendors" :statuses="statuses" base-path="/admin/hotel/reviews" admin /></AdminLayout>
</template>
