<script setup>
import { computed } from 'vue';
import SeoHead from '@/Components/SeoHead.vue';
import PublicLayout from '../Layouts/PublicLayout.vue';
import HomepageSectionRenderer from '../Components/Public/Home/HomepageSectionRenderer.vue';

const props = defineProps({
    homepage: { type: Object, default: () => ({ sections: [] }) },
});

const sections = computed(() => Array.isArray(props.homepage?.sections) ? props.homepage.sections : []);
</script>

<template>
    <PublicLayout>
        <SeoHead />
        <HomepageSectionRenderer :sections="sections" />
    </PublicLayout>
</template>
