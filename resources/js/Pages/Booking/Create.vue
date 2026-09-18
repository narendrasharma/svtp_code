<script setup>
import { computed, ref, watch } from 'vue';
import { Link, useForm } from '@inertiajs/vue3';
import axios from 'axios';
import AppLayout from '../../Layouts/AppLayout.vue';
import SeoHead from '../../Components/SeoHead.vue';
import { appUrl } from '../../appUrl';

const props = defineProps({
    package: Object,
    customer: { type: Object, default: null },
    addons: { type: Array, default: () => [] },
    availability: { type: Object, default: () => ({}) },
});

const form = useForm({
    package_id: props.package.id,
    travel_date: '',
    total_adults: 1,
    total_children: 0,
    customer_name: props.customer?.name ?? '',
    customer_email: props.customer?.email ?? '',
    customer_phone: props.customer?.phone ?? '',
    country: '',
    pickup_address: '',
    special_requests: '',
    coupon_code: '',
    addons: [],
});

const today = new Date().toISOString().slice(0, 10);
const unitPrice = computed(() => Number(props.package.effective_price || 0));
// Instant presentation-only estimate; the server quote below is authoritative.
const clientTotal = computed(() => unitPrice.value * (Number(form.total_adults || 0) + Number(form.total_children || 0) * 0.5));

const serverQuote = ref(null);
const quoteLoading = ref(false);
const quoteError = ref(null);
let quoteTimer = null;
async function fetchQuote() {
    clearTimeout(quoteTimer);
    quoteTimer = setTimeout(async () => {
        if (!form.total_adults || Number(form.total_adults) < 1) return;
        quoteLoading.value = true;
        quoteError.value = null;
        try {
            const { data } = await axios.post(appUrl('/booking/estimate'), {
                package_id: props.package.id,
                total_adults: Number(form.total_adults),
                total_children: Number(form.total_children || 0),
                travel_date: form.travel_date || undefined,
                coupon_code: form.coupon_code || undefined,
                customer_email: form.customer_email || undefined,
                addons: form.addons,
            });
            serverQuote.value = data;
        } catch (e) {
            serverQuote.value = null;
            quoteError.value = e?.response?.data?.errors?.coupon_code?.[0] ?? null;
        } finally {
            quoteLoading.value = false;
        }
    }, 400);
}
watch([() => form.total_adults, () => form.total_children, () => form.travel_date, () => form.coupon_code, () => form.addons], fetchQuote, { immediate: true, deep: true });

const displayTotal = computed(() => serverQuote.value ? Number(serverQuote.value.total_amount) : clientTotal.value);

function toggleAddon(addon) {
    const index = form.addons.findIndex((row) => Number(row.addon_id) === Number(addon.id));
    if (index >= 0) {
        form.addons.splice(index, 1);
    } else {
        form.addons.push({ addon_id: addon.id, quantity: 1 });
    }
    fetchQuote();
}

function addonChecked(addon) {
    return form.addons.some((row) => Number(row.addon_id) === Number(addon.id));
}

function addonQuantity(addon) {
    return form.addons.find((row) => Number(row.addon_id) === Number(addon.id))?.quantity ?? 1;
}

function setQuantity(addon, quantity) {
    const row = form.addons.find((r) => Number(r.addon_id) === Number(addon.id));
    if (row) row.quantity = Math.max(1, Math.min(Number(addon.max_quantity || 30), Number(quantity || 1)));
}

function submit() {
    form.post(appUrl('/bookings'));
}
</script>

<template>
    <AppLayout>
        <SeoHead :title="`Book ${package.title}`" noindex />
        <div class="container py-5">
            <Link :href="appUrl(`/packages/${package.slug}`)" class="small text-muted text-decoration-none"><i class="bi bi-arrow-left me-1"></i>Back to tour</Link>
            <p class="section-eyebrow mt-3">Book your tour</p>
            <h1 class="section-title mb-1">{{ package.title }}</h1>
            <p class="text-muted">No account needed — booking takes a minute.</p>

            <form @submit.prevent="submit">
                <div class="row g-4 mt-2">
                    <div class="col-lg-7">
                        <div class="glass-card p-4 mb-4">
                            <h5 class="text-svtp mb-3"><i class="bi bi-calendar-event me-2"></i>Travel Details</h5>
                            <div class="row g-3">
                                <div class="col-md-4"><label for="travel-date" class="form-label">Travel Date*</label><input id="travel-date" v-model="form.travel_date" type="date" :min="today" class="form-control" required /><small class="text-danger">{{ form.errors.travel_date }}</small></div>
                                <div class="col-md-4"><label for="adults" class="form-label">Adults*</label><input id="adults" v-model.number="form.total_adults" type="number" min="1" max="30" class="form-control" required /><small class="text-danger">{{ form.errors.total_adults }}</small></div>
                                <div class="col-md-4"><label for="children" class="form-label">Children (under 12)</label><input id="children" v-model.number="form.total_children" type="number" min="0" max="30" class="form-control" /><small class="text-danger">{{ form.errors.total_children }}</small></div>
                                <div class="col-12"><label for="pickup" class="form-label">Pickup Location</label><input id="pickup" v-model="form.pickup_address" class="form-control" maxlength="255" placeholder="Hotel, station or airport" /><small class="text-danger">{{ form.errors.pickup_address }}</small></div>
                                <div class="col-12"><label for="requests" class="form-label">Special Requests</label><textarea id="requests" v-model="form.special_requests" class="form-control" rows="2" maxlength="1000" placeholder="Anything we should know?"></textarea><small class="text-danger">{{ form.errors.special_requests }}</small></div>
                            </div>
                            <small class="text-danger d-block mt-2">{{ form.errors.package_id }}</small>
                        </div>

                        <div v-if="addons.length" class="glass-card p-4 mb-4">
                            <h5 class="text-svtp mb-1"><i class="bi bi-plus-circle me-2"></i>Enhance Your Tour</h5>
                            <p class="small text-muted">Optional extras — priced server-side.</p>
                            <div v-for="addon in addons" :key="addon.id" class="border rounded p-3 mb-2">
                                <div class="d-flex gap-2 align-items-start">
                                    <input :id="`addon-${addon.id}`" :checked="addonChecked(addon) || addon.is_required" :disabled="addon.is_required" type="checkbox" class="form-check-input mt-1" @change="toggleAddon(addon)" />
                                    <div class="flex-grow-1">
                                        <label :for="`addon-${addon.id}`" class="fw-semibold small">{{ addon.name }} <span class="text-muted">· ₹{{ addon.price }} {{ addon.pricing_type === 'fixed' ? 'once' : addon.pricing_type === 'per_person' ? '/ person' : '/ qty' }}</span></label>
                                        <p v-if="addon.description" class="small text-muted mb-1">{{ addon.description }}</p>
                                        <div v-if="addonChecked(addon) && addon.pricing_type === 'per_quantity'" class="d-flex align-items-center gap-2 mt-1">
                                            <label :for="`qty-${addon.id}`" class="small text-muted mb-0">Qty</label>
                                            <input :id="`qty-${addon.id}`" :value="addonQuantity(addon)" type="number" min="1" :max="addon.max_quantity || 30" class="form-control form-control-sm" style="max-width: 90px;" @input="setQuantity(addon, $event.target.value)" />
                                        </div>
                                        <span v-if="addon.is_required" class="badge bg-info text-dark mt-1">Included automatically</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="glass-card p-4">
                            <h5 class="text-svtp mb-3"><i class="bi bi-person-lines-fill me-2"></i>Customer Details</h5>
                            <div class="row g-3">
                                <div class="col-md-6"><label for="customer-name" class="form-label">Full Name*</label><input id="customer-name" v-model="form.customer_name" class="form-control" maxlength="255" required /><small class="text-danger">{{ form.errors.customer_name }}</small></div>
                                <div class="col-md-6"><label for="phone" class="form-label">Phone*</label><input id="phone" v-model="form.customer_phone" class="form-control" maxlength="20" required /><small class="text-danger">{{ form.errors.customer_phone }}</small></div>
                                <div class="col-md-6"><label for="email" class="form-label">Email</label><input id="email" v-model="form.customer_email" type="email" class="form-control" maxlength="255" /><small class="text-danger">{{ form.errors.customer_email }}</small></div>
                                <div class="col-md-6"><label for="country" class="form-label">Country</label><input id="country" v-model="form.country" class="form-control" maxlength="100" /><small class="text-danger">{{ form.errors.country }}</small></div>
                            </div>
                            <p v-if="!customer" class="small text-muted mt-3 mb-0">Already have an account? <a :href="appUrl('/admin')">Sign in</a> to link this booking to it — or continue as guest. New here? <a :href="appUrl('/register')">Create an account</a>.</p>
                            <p v-else class="small text-muted mt-3 mb-0"><i class="bi bi-check-circle-fill text-success me-1"></i>Booking as {{ customer.name }} — details above remain editable for this booking.</p>
                        </div>
                    </div>

                    <div class="col-lg-5">
                        <div class="glass-card p-4 booking-sticky">
                            <h5 class="text-svtp">Price Summary</h5>
                            <p class="small text-muted mb-3">₹{{ Number(unitPrice).toLocaleString('en-IN') }} per adult · children half price</p>
                            <div class="mb-3">
                                <label for="coupon" class="form-label small">Promo Code</label>
                                <div class="d-flex gap-2">
                                    <input id="coupon" v-model="form.coupon_code" class="form-control" maxlength="50" placeholder="e.g. SAVE10" />
                                    <button type="button" class="btn btn-outline-svtp" @click="fetchQuote">Apply</button>
                                </div>
                                <small v-if="quoteError" class="text-danger">{{ quoteError }}</small>
                                <small v-else-if="serverQuote?.coupon" class="text-success">Applied {{ serverQuote.coupon.code }} — verified server-side.</small>
                                <small class="text-danger d-block">{{ form.errors.coupon_code }}</small>
                            </div>
                            <div v-if="serverQuote" class="small">
                                <div class="d-flex justify-content-between py-1"><span>Adults ({{ serverQuote.total_adults }} × ₹{{ Number(serverQuote.base_price).toLocaleString('en-IN') }})</span><span>₹{{ (serverQuote.base_price * serverQuote.total_adults).toLocaleString('en-IN') }}</span></div>
                                <div v-if="serverQuote.total_children" class="d-flex justify-content-between py-1"><span>Children ({{ serverQuote.total_children }} × ₹{{ Number(serverQuote.child_unit_price).toLocaleString('en-IN') }})</span><span>₹{{ (serverQuote.child_unit_price * serverQuote.total_children).toLocaleString('en-IN') }}</span></div>
                                <div v-if="serverQuote.addons?.length" class="mt-2">
                                    <div v-for="line in serverQuote.addons" :key="line.addon_id" class="d-flex justify-content-between py-1"><span>{{ line.name }} × {{ line.quantity }}</span><span>₹{{ Number(line.total).toLocaleString('en-IN') }}</span></div>
                                </div>
                                <div class="d-flex justify-content-between py-1"><span>Subtotal</span><span>₹{{ Number(serverQuote.subtotal).toLocaleString('en-IN') }}</span></div>
                                <div v-if="Number(serverQuote.discount_amount)" class="d-flex justify-content-between py-1 text-success"><span>Discount{{ serverQuote.coupon ? ` (${serverQuote.coupon.code})` : '' }}</span><span>−₹{{ Number(serverQuote.discount_amount).toLocaleString('en-IN') }}</span></div>
                                <div v-if="Number(serverQuote.tax_amount)" class="d-flex justify-content-between py-1"><span>Tax</span><span>₹{{ Number(serverQuote.tax_amount).toLocaleString('en-IN') }}</span></div>
                                <div v-if="serverQuote.availability && !serverQuote.availability.bookable" class="alert alert-warning small mt-2 mb-0">{{ serverQuote.availability.reason }}</div>
                            </div>
                            <hr />
                            <div class="d-flex justify-content-between align-items-center">
                                <span class="fw-semibold">Total <small v-if="quoteLoading" class="text-muted fw-normal">(updating…)</small></span>
                                <span class="price-tag fs-4 mb-0">₹{{ Number(displayTotal).toLocaleString('en-IN') }}</span>
                            </div>
                            <p class="small text-muted mt-2 mb-3">Verified server price — recalculated securely when you confirm.</p>
                            <button class="btn btn-svtp w-100" :disabled="form.processing">{{ form.processing ? 'Creating your booking…' : 'Confirm Booking' }}</button>
                            <div v-if="Object.keys(form.errors).length" class="alert alert-danger small mt-3 mb-0" role="alert">Please correct the highlighted fields above.</div>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </AppLayout>
</template>
