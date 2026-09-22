<script setup>
import { Link } from '@inertiajs/vue3';
import AdminLayout from '../../../../Layouts/AdminLayout.vue';
import { appUrl } from '../../../../appUrl';
defineProps({ property: Object });
function stars(n) {
    if (n === null || n === undefined) return 'Unrated';
    return '★'.repeat(Number(n)) + '☆'.repeat(5 - Number(n));
}
</script>
<template><AdminLayout><div class="container-fluid py-3">
<Link :href="appUrl('/admin/hotel/properties')">← All properties</Link>
<Link :href="appUrl(`/admin/hotel/properties/${property.id}/edit`)" class="btn btn-sm btn-outline-secondary ms-2">Edit</Link>
<Link :href="appUrl(`/admin/hotel/properties/${property.id}/room-types`)" class="btn btn-sm btn-outline-primary ms-2">Room types</Link>
<Link :href="appUrl(`/admin/hotel/properties/${property.id}/units`)" class="btn btn-sm btn-outline-primary ms-2">Room units</Link>
<h2 class="my-3">{{ property.name }}</h2>
<div class="row g-3">
<div class="col-lg-8">
<div class="card p-3 mb-3"><h5>Overview</h5>
<p class="mb-1"><strong>{{ property.property_type?.name }}</strong> · {{ stars(property.star_rating) }} · Status: <strong>{{ property.status }}</strong></p>
<p class="mb-1">{{ property.address_line_1 }}<span v-if="property.address_line_2">, {{ property.address_line_2 }}</span></p>
<p class="mb-1">{{ property.city?.name }}<span v-if="property.state">, {{ property.state.name }}</span> {{ property.postal_code }} {{ property.country_code }}</p>
<p v-if="property.short_description" class="mt-2">{{ property.short_description }}</p>
<div v-if="property.description" v-html="property.description" class="mt-2"></div>
</div>
<div class="card p-3 mb-3"><h5>Amenities ({{ property.amenities?.length ?? 0 }})</h5>
<p class="mb-0">{{ (property.amenities ?? []).map(a => a.name).join(' · ') || 'None attached.' }}</p></div>
<div class="card p-3 mb-3"><h5>Gallery ({{ property.images?.length ?? 0 }})</h5>
<div class="d-flex flex-wrap gap-2"><img v-for="img in property.images" :key="img.id" :src="appUrl(`/storage/${img.path}`)" :alt="img.alt_text ?? property.name" width="140" class="rounded" /></div>
<p v-if="!property.images?.length" class="text-muted mb-0">No images.</p></div>
</div>
<div class="col-lg-4">
<div class="card p-3 mb-3"><h5>Stay defaults</h5>
<p class="mb-1">Check-in: {{ property.check_in_time?.slice(0, 5) ?? '—' }} · Check-out: {{ property.check_out_time?.slice(0, 5) ?? '—' }}</p>
<p class="mb-1">Timezone: {{ property.timezone ?? '—' }} · Currency: {{ property.currency ?? '—' }}</p>
<p class="mb-0">Phone: {{ property.phone ?? '—' }} · Email: {{ property.email ?? '—' }}<br />Website: {{ property.website ?? '—' }}</p></div>
<div class="card p-3 mb-3"><h5>Policies</h5>
<p class="mb-1">Children: {{ property.children_policy ?? '—' }}</p>
<p class="mb-1">Pets: {{ property.pet_policy ?? '—' }}</p>
<p class="mb-1">Smoking: {{ property.smoking_policy ?? '—' }}</p>
<p class="mb-1">Check-in instructions: {{ property.check_in_instructions ?? '—' }}</p>
<p class="mb-0">House rules: {{ property.house_rules ?? '—' }}</p></div>
<div class="card p-3"><h5>SEO</h5>
<p class="mb-1">Meta title: {{ property.meta_title ?? '—' }}</p>
<p class="mb-0">Meta description: {{ property.meta_description ?? '—' }}</p></div>
</div>
</div>
</div></AdminLayout></template>
