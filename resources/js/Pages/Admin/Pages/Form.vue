<script setup>
import { Link, useForm } from '@inertiajs/vue3';
import { ref, watch } from 'vue';
import AdminLayout from '../../../Layouts/AdminLayout.vue';
import { appUrl } from '../../../appUrl';
import { Ckeditor } from '@ckeditor/ckeditor5-vue';
import {
    ClassicEditor,
    Essentials,
    Paragraph,
    Heading,
    Bold,
    Italic,
    Link as EditorLink,
    List,
    BlockQuote,
    Undo,
    Image,
    ImageToolbar,
    ImageCaption,
    ImageStyle,
    ImageResize,
    ImageUpload,
    SimpleUploadAdapter,
} from 'ckeditor5';

import 'ckeditor5/ckeditor5.css';

const props = defineProps({
    page: { type: Object, default: null },
    templates: { type: Array, default: () => [] },
});

const form = useForm({
    title: props.page?.title ?? '',
    slug: props.page?.slug ?? '',
    template: props.page?.template ?? (props.templates[0] ?? 'default'),
    excerpt: props.page?.excerpt ?? '',
    content: props.page?.content ?? '',
    is_active: props.page?.is_active ?? true,
    sort_order: props.page?.sort_order ?? 0,
    meta_title: props.page?.meta_title ?? '',
    meta_description: props.page?.meta_description ?? '',
});

const slugManuallyEdited = ref(!!props.page);

watch(() => form.title, (title) => {
    if (!props.page && !slugManuallyEdited.value) {
        form.slug = title
            .toLowerCase()
            .trim()
            .replace(/[^a-z0-9]+/g, '-')
            .replace(/^-|-$/g, '');
    }
});

function markSlugEdited() {
    slugManuallyEdited.value = true;
}

const editor = ClassicEditor;

const editorConfig = {
    licenseKey: 'GPL',
    plugins: [
        Essentials,
        Paragraph,
        Heading,
        Bold,
        Italic,
        EditorLink,
        List,
        BlockQuote,
        Undo,
        Image,
        ImageToolbar,
        ImageCaption,
        ImageStyle,
        ImageResize,
        ImageUpload,
        SimpleUploadAdapter,
    ],
    toolbar: [
        'undo',
        'redo',
        '|',
        'heading',
        '|',
        'bold',
        'italic',
        'link',
        '|',
        'bulletedList',
        'numberedList',
        'blockQuote',
        '|',
        'imageUpload',
    ],
    simpleUpload: {
        uploadUrl: appUrl('/admin/editor/upload-image'),
        headers: {
            'X-CSRF-TOKEN': document
                .querySelector('meta[name="csrf-token"]')
                ?.getAttribute('content'),
        },
    },
    image: {
        upload: {
            types: ['jpeg', 'png', 'webp'],
        },
        toolbar: [
            'imageTextAlternative',
            'toggleImageCaption',
            '|',
            'imageStyle:inline',
            'imageStyle:block',
            'imageStyle:side',
        ],
    },
};

function templateLabel(value) {
    return value
        .split(/[-_]/)
        .map((segment) => segment.charAt(0).toUpperCase() + segment.slice(1))
        .join(' ');
}

function submit() {
    const url = props.page
        ? `${appUrl('/admin/pages')}/${props.page.id}`
        : appUrl('/admin/pages');

    if (props.page) {
        form.put(url);
    } else {
        form.post(url);
    }
}
</script>

<template>
    <AdminLayout>
        <h2>{{ page ? 'Edit' : 'New' }} Page</h2>
        <form class="mt-3" style="max-width: 720px;" @submit.prevent="submit">
            <label class="form-label">Title</label>
            <input v-model="form.title" class="form-control" required>
            <small class="text-danger">{{ form.errors.title }}</small>

            <label class="form-label mt-3">Slug</label>
            <input v-model="form.slug" class="form-control" required @input="markSlugEdited">
            <small class="text-danger">{{ form.errors.slug }}</small>
            <div class="form-text">Permalink: <code>{{ appUrl(`/${form.slug || 'your-page-slug'}`) }}</code></div>

            <label class="form-label mt-3">Template</label>
            <select v-model="form.template" class="form-select" required>
                <option v-for="template in templates" :key="template" :value="template">
                    {{ templateLabel(template) }}
                </option>
            </select>
            <small class="text-danger">{{ form.errors.template }}</small>

            <label class="form-label mt-3">Excerpt</label>
            <textarea
                v-model="form.excerpt"
                class="form-control"
                rows="3"
                placeholder="Short summary for listings or previews."
            ></textarea>
            <small class="text-danger">{{ form.errors.excerpt }}</small>

            <div class="mt-3">
                <label class="form-label fw-semibold">Page Content</label>
                <Ckeditor
                    v-model="form.content"
                    :editor="editor"
                    :config="editorConfig"
                />
                <small class="text-danger">{{ form.errors.content }}</small>
            </div>

            <label class="form-label mt-3">Display order</label>
            <input v-model.number="form.sort_order" type="number" min="0" class="form-control">
            <small class="text-danger">{{ form.errors.sort_order }}</small>

            <div class="form-check mt-3">
                <input id="page-active" v-model="form.is_active" type="checkbox" class="form-check-input">
                <label for="page-active" class="form-check-label">Active</label>
            </div>
            <small class="text-danger">{{ form.errors.is_active }}</small>

            <div class="card mt-4">
                <div class="card-header">
                    <strong>
                        <i class="bi bi-search me-2"></i>
                        SEO Settings
                    </strong>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-label">
                            Meta Title
                        </label>
                        <input
                            v-model="form.meta_title"
                            type="text"
                            class="form-control"
                            maxlength="70"
                            placeholder="Leave blank to use the page title automatically"
                        >
                        <div class="d-flex justify-content-between mt-1">
                            <small class="text-muted">
                                Leave blank to use the page title automatically.
                            </small>
                            <small class="text-muted">
                                {{ form.meta_title.length }}/70
                            </small>
                        </div>
                        <small class="text-danger">
                            {{ form.errors.meta_title }}
                        </small>
                    </div>

                    <div>
                        <label class="form-label">
                            Meta Description
                        </label>
                        <textarea
                            v-model="form.meta_description"
                            class="form-control"
                            rows="3"
                            maxlength="170"
                            placeholder="Leave blank to generate from the page content."
                        ></textarea>
                        <div class="d-flex justify-content-between mt-1">
                            <small class="text-muted">
                                Leave blank to generate from the page content.
                            </small>
                            <small class="text-muted">
                                {{ form.meta_description.length }}/170
                            </small>
                        </div>
                        <small class="text-danger">
                            {{ form.errors.meta_description }}
                        </small>
                    </div>
                </div>
            </div>

            <div class="d-flex gap-2 mt-4">
                <button class="btn btn-svtp" :disabled="form.processing">Save Page</button>
                <Link :href="appUrl('/admin/pages')" class="btn btn-outline-secondary">Cancel</Link>
            </div>
        </form>
    </AdminLayout>
</template>
