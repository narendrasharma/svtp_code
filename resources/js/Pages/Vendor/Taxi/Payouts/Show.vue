<script setup>
import VendorLayout from '../../../../Layouts/VendorLayout.vue';
import PayoutShow from '../../../../Components/Taxi/PayoutShow.vue';
defineProps({ payout: Object });
</script>
<template><VendorLayout><PayoutShow :payout="payout" portal="vendor" /></VendorLayout></template>
