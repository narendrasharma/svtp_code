<script setup>
import VendorLayout from '../../../../Layouts/VendorLayout.vue';
import PayoutIndex from '../../../../Components/Taxi/PayoutIndex.vue';
defineProps({ payouts: Object, filters: Object, statuses: Array });
</script>
<template><VendorLayout><PayoutIndex :payouts="payouts" :filters="filters" :statuses="statuses" portal="vendor" /></VendorLayout></template>
