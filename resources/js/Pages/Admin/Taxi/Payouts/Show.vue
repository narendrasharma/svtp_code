<script setup>
import AdminLayout from '../../../../Layouts/AdminLayout.vue';
import PayoutShow from '../../../../Components/Taxi/PayoutShow.vue';
defineProps({ payout: Object });
</script>
<template><AdminLayout><PayoutShow :payout="payout" portal="admin" /></AdminLayout></template>
