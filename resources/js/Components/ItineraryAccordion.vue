<script setup>
defineProps({ days: { type: Array, default: () => [] } });
</script>

<template>
    <div class="accordion" id="itineraryAccordion">
        <div v-for="(day, i) in days" :key="i" class="accordion-item">
            <h2 class="accordion-header">
                <button
                    class="accordion-button"
                    :class="{ collapsed: i !== 0 }"
                    type="button"
                    data-bs-toggle="collapse"
                    :data-bs-target="`#day-${i}`"
                >
                    Day {{ day.day }}: {{ day.title }}
                </button>
            </h2>
            <div :id="`day-${i}`" class="accordion-collapse collapse" :class="{ show: i === 0 }" data-bs-parent="#itineraryAccordion">
                <div class="accordion-body">
                    <ul>
                        <li v-for="(point, j) in day.points" :key="j">{{ point }}</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</template>
