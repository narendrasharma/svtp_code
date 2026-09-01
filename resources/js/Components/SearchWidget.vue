<script setup>
import { reactive } from 'vue';
import { router } from '@inertiajs/vue3';
import { appUrl } from '../appUrl';

defineProps({ cities: { type: Array, default: () => [] } });

const form = reactive({
    city: '',
    pickup: '',
    date: '',
    adults: 2,
    children: 0,
});

function search() {
    router.get(appUrl('/packages'), form);
}
</script>

<template>
    <div class="search-pill">
        <div class="row g-3 align-items-end">
            <div class="col-6 col-md-2">
                <label class="form-label d-block">Pickup From</label>
                <input v-model="form.pickup" type="text" placeholder="Delhi, Agra..." class="form-control" />
            </div>
            <div class="col-6 col-md-2">
                <label class="form-label d-block">Destination</label>
                <select v-model="form.city" class="form-select">
                    <option value="">Any city</option>
                    <option v-for="c in cities" :key="c.id" :value="c.slug">{{ c.name }}</option>
                </select>
            </div>
            <div class="col-6 col-md-3">
                <label class="form-label d-block">Travel Date</label>
                <input v-model="form.date" type="date" class="form-control" />
            </div>
            <div class="col-3 col-md-2">
                <label class="form-label d-block">Adults</label>
                <input v-model.number="form.adults" type="number" min="1" class="form-control" />
            </div>
            <div class="col-3 col-md-1">
                <label class="form-label d-block">Kids</label>
                <input v-model.number="form.children" type="number" min="0" class="form-control" />
            </div>
            <div class="col-12 col-md-2">
                <button class="btn btn-svtp w-100" @click="search">Search Tours</button>
            </div>
        </div>
    </div>
</template>
