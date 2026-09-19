<script setup>
import { onBeforeUnmount, onMounted, ref, watch } from 'vue';

const props = defineProps({
    map: { type: Object, default: () => ({ provider: 'none', enabled: false, public_key: null }) },
    markers: { type: Array, default: () => [] },
    path: { type: Array, default: () => [] },
    height: { type: String, default: '280px' },
});

const container = ref(null);
const loadError = ref('');
let mapInstance = null;
let overlays = [];
let googlePromise = null;

function loadGoogle(key) {
    if (window.google && window.google.maps) {
        return Promise.resolve();
    }
    if (!googlePromise) {
        googlePromise = new Promise((resolve, reject) => {
            const script = document.createElement('script');
            script.src = `https://maps.googleapis.com/maps/api/js?key=${encodeURIComponent(key)}`;
            script.async = true;
            script.defer = true;
            script.onload = () => resolve();
            script.onerror = () => reject(new Error('Map SDK failed to load.'));
            document.head.appendChild(script);
        });
    }
    return googlePromise;
}

function clearOverlays() {
    overlays.forEach(item => item.setMap(null));
    overlays = [];
}

function renderGoogle() {
    if (!container.value || !window.google) {
        return;
    }
    const points = props.markers
        .filter(marker => Number.isFinite(marker.lat) && Number.isFinite(marker.lng))
        .map(marker => ({ lat: Number(marker.lat), lng: Number(marker.lng) }));

    (props.path || []).forEach(point => {
        if (Number.isFinite(point.lat) && Number.isFinite(point.lng)) {
            points.push({ lat: Number(point.lat), lng: Number(point.lng) });
        }
    });

    const fallback = { lat: 27.1751, lng: 78.0421 };
    const center = points.length ? points[0] : fallback;

    if (!mapInstance) {
        mapInstance = new window.google.maps.Map(container.value, { center, zoom: 13 });
    } else {
        mapInstance.setCenter(center);
    }

    clearOverlays();
    const bounds = new window.google.maps.LatLngBounds();

    props.markers.forEach(marker => {
        if (!Number.isFinite(marker.lat) || !Number.isFinite(marker.lng)) {
            return;
        }
        const pin = new window.google.maps.Marker({
            position: { lat: Number(marker.lat), lng: Number(marker.lng) },
            map: mapInstance,
            title: marker.label || marker.kind,
        });
        overlays.push(pin);
        bounds.extend(pin.getPosition());
    });

    if ((props.path || []).length > 1) {
        const line = new window.google.maps.Polyline({
            path: props.path.map(point => ({ lat: Number(point.lat), lng: Number(point.lng) })),
            map: mapInstance,
        });
        overlays.push(line);
        props.path.forEach(point => bounds.extend({ lat: Number(point.lat), lng: Number(point.lng) }));
    }

    if (!bounds.isEmpty()) {
        mapInstance.fitBounds(bounds);
    }
}

async function render() {
    loadError.value = '';
    if (!props.map || props.map.provider === 'none' || !props.map.enabled) {
        return;
    }
    if (props.map.provider === 'google') {
        if (!props.map.public_key) {
            loadError.value = 'Map key missing. Ask the administrator to configure the browser Maps key.';
            return;
        }
        try {
            await loadGoogle(props.map.public_key);
            renderGoogle();
        } catch (e) {
            loadError.value = 'Map failed to load. Coordinates below remain available.';
        }
        return;
    }
    loadError.value = 'This map provider is not supported yet.';
}

onMounted(render);
watch(() => [props.markers, props.path, props.map], render, { deep: true });
onBeforeUnmount(() => {
    clearOverlays();
    mapInstance = null;
});
</script>

<template>
    <div>
        <div v-if="!map || map.provider === 'none' || !map.enabled" class="map-fallback">
            Map provider not configured — coordinates and freshness below remain fully usable.
        </div>
        <div v-else>
            <div ref="container" class="map-canvas" :style="{ height }"></div>
            <div v-if="loadError" class="alert alert-warning py-2 small mt-2">{{ loadError }}</div>
        </div>
    </div>
</template>

<style scoped>
.map-canvas { width: 100%; border-radius: .75rem; background: #0b1526; min-height: 200px; }
.map-fallback { border: 1px dashed rgba(148, 163, 184, .35); border-radius: .75rem; padding: 1rem; color: #94a3b8; font-size: .875rem; text-align: center; }
</style>
