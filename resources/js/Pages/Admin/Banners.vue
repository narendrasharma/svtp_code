    <script setup>
    import { appUrl } from '../../appUrl';
    import AdminLayout from '../../Layouts/AdminLayout.vue';
    import { Link, router, useForm } from '@inertiajs/vue3';

    import { ref } from 'vue';

    const props = defineProps({
        banners: Array
    });

    const bannersList = ref(
        props.banners.map(banner => ({ ...banner }))
    );

    const form = useForm({
        image: null,
        title: '',
        subtitle: '',
        cta_label: '',
        cta_link: '',
    });

    function removeBanner(b) {
        if (window.confirm(`Delete banner "${b.title || 'Untitled'}"?`)) {
            router.delete(
                `${appUrl('/admin/banners')}/${b.id}`
            );
        }
    }
    function submit() {
        form.post(appUrl('/admin/banners'), {
            forceFormData: true
        });
    }


    function updateOrder(banner) {
        router.patch(
            `${appUrl('/admin/banners')}/${banner.id}/order`,
            {
                sort_order: banner.sort_order
            },
            {
                preserveScroll: true
            }
        );
    }
    </script>

    <template>
        <AdminLayout>
            <h2>Home Banners</h2>
            <form @submit.prevent="submit" class="mb-4" style="max-width: 480px;">

                <input
                    type="file"
                    class="form-control mb-2"
                    accept="image/jpeg,image/png,image/webp"
                    @input="form.image = $event.target.files[0]"
                />

                <small class="text-danger d-block mb-2">
                    {{ form.errors.image }}
                </small>

                <input
                    v-model="form.title"
                    class="form-control mb-2"
                    placeholder="Banner Title"
                />

                <small class="text-danger d-block mb-2">
                    {{ form.errors.title }}
                </small>

                <input
                    v-model="form.subtitle"
                    class="form-control mb-2"
                    placeholder="Banner Subtitle"
                />

                <small class="text-danger d-block mb-2">
                    {{ form.errors.subtitle }}
                </small>

                <input
                    v-model="form.cta_label"
                    class="form-control mb-2"
                    placeholder="Button Text e.g. Explore Tours"
                />

                <small class="text-danger d-block mb-2">
                    {{ form.errors.cta_label }}
                </small>

                <input
                    v-model="form.cta_link"
                    class="form-control mb-2"
                    placeholder="Button Link e.g. /packages"
                />

                <small class="text-danger d-block mb-2">
                    {{ form.errors.cta_link }}
                </small>

                <button
                    class="btn btn-svtp"
                    :disabled="form.processing"
                >
                    {{ form.processing ? 'Uploading...' : 'Upload Banner' }}
                </button>

            </form>
            <div class="row g-3">
<!--                <div v-for="b in banners" :key="b.id" class="col-md-3">
                    <img :src="`${appUrl('/storage')}/${b.image_path}`" class="img-fluid rounded" />
                </div>-->

                <div class="table-responsive mt-4">
                    <table class="table align-middle">
                        <thead>
                        <tr>
                            <th>#</th>
                            <th>Image</th>
                            <th>Title</th>
                            <th>Subtitle</th>
                            <th>CTA</th>
                            <th>Order</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                        </thead>

                        <tbody>
                        <tr
                            v-for="(b, index) in bannersList"
                            :key="b.id"
                        >
                            <td>{{ index + 1 }}</td>

                            <td>
                                <img
                                    :src="`${appUrl('/storage')}/${b.image_path}`"
                                    :alt="b.title || 'Banner'"
                                    class="rounded border"
                                    style="width: 120px; height: 65px; object-fit: cover;"
                                />
                            </td>

                            <td>
                                {{ b.title || '-' }}
                            </td>

                            <td>
                    <span class="text-muted small">
                        {{ b.subtitle || '-' }}
                    </span>
                            </td>

                            <td>
                                <div v-if="b.cta_label">
                                    <strong>{{ b.cta_label }}</strong>

                                    <div
                                        v-if="b.cta_link"
                                        class="small text-muted"
                                    >
                                        {{ b.cta_link }}
                                    </div>
                                </div>

                                <span v-else>-</span>
                            </td>

                            <td>
<!--                                {{ b.sort_order }}-->
                                <input
                                    v-model.number="b.sort_order"
                                    type="number"
                                    min="1"
                                    class="form-control form-control-sm"
                                    style="width: 70px;"
                                    @change="updateOrder(b)"
                                />
                            </td>

                            <td>
                    <span
                        class="badge"
                        :class="b.is_active ? 'bg-success' : 'bg-secondary'"
                    >
                        {{ b.is_active ? 'Active' : 'Inactive' }}
                    </span>
                            </td>

                            <td>
                                 <button
                                    type="button"
                                    class="btn btn-sm btn-outline-danger"
                                    @click="removeBanner(b)"
                                >
                                    Delete
                                </button>
                            </td>
                        </tr>

                        <tr v-if="!banners.length">
                            <td
                                colspan="8"
                                class="text-center text-muted py-4"
                            >
                                No banners added yet.
                            </td>
                        </tr>
                        </tbody>
                    </table>
                </div>


            </div>
        </AdminLayout>
    </template>
