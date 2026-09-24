<script setup>
import { Ckeditor } from '@ckeditor/ckeditor5-vue';
import { BlockQuote, Bold, ClassicEditor, Essentials, Heading, Image, ImageCaption, ImageResize, ImageStyle, ImageToolbar, ImageUpload, Italic, Link, List, Paragraph, SimpleUploadAdapter, Undo } from 'ckeditor5';
import 'ckeditor5/ckeditor5.css';
import { appUrl } from '../../appUrl';

defineProps({ modelValue: { type: String, default: '' } });
const emit = defineEmits(['update:modelValue']);
const editorConfig = {
    licenseKey: 'GPL',
    plugins: [Essentials, Paragraph, Heading, Bold, Italic, Link, List, BlockQuote, Undo, Image, ImageToolbar, ImageCaption, ImageStyle, ImageResize, ImageUpload, SimpleUploadAdapter],
    toolbar: ['undo', 'redo', '|', 'heading', '|', 'bold', 'italic', 'link', '|', 'bulletedList', 'numberedList', 'blockQuote', '|', 'imageUpload'],
    simpleUpload: { uploadUrl: appUrl('/admin/editor/upload-image'), headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') } },
    image: { upload: { types: ['jpeg', 'png', 'webp'] }, toolbar: ['imageTextAlternative', 'toggleImageCaption', '|', 'imageStyle:inline', 'imageStyle:block', 'imageStyle:side'] },
};
</script>
<template>
    <Ckeditor :editor="ClassicEditor" :config="editorConfig" :model-value="modelValue" @update:model-value="emit('update:modelValue', $event)" />
</template>
<style scoped>
:deep(.ck-editor__editable_inline) { min-height: 220px; }
</style>
