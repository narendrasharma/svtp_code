<script setup>
import AdminLayout from '../../../../Layouts/AdminLayout.vue';
import PayoutIndex from '../../../../Components/Taxi/PayoutIndex.vue';
defineProps({ payouts: Object, filters: Object, statuses: Array });
</script>
<template><AdminLayout><PayoutIndex :payouts="payouts" :filters="filters" :statuses="statuses" portal="admin" /></AdminLayout></template>
