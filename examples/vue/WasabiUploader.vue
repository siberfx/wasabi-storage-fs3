<!--
    resources/js/components/WasabiUploader.vue  (Vue 3, <script setup>)

    Works in a plain Vite + Vue app or with Inertia. Needs a
    <meta name="csrf-token" content="{{ csrf_token() }}"> tag in the layout.

    Usage:  <WasabiUploader accept="image/*,application/pdf" />
-->
<script setup>
import { onMounted, reactive, ref } from 'vue';
import { deleteWasabiFile, listWasabiFiles, uploadToWasabi } from '../wasabiUpload';

defineProps({
    accept: { type: String, default: '' },
});

const emit = defineEmits(['uploaded']);

const files = ref([]);
const uploads = ref([]); // { id, name, progress, error }
const loading = ref(true);
const dragging = ref(false);

const isImage = (name) => /\.(jpe?g|png|webp|gif)$/i.test(name);
const formatSize = (bytes) => (bytes < 1024 * 1024 ? `${(bytes / 1024).toFixed(1)} KB` : `${(bytes / 1024 / 1024).toFixed(1)} MB`);

async function refresh() {
    loading.value = true;
    try {
        files.value = await listWasabiFiles();
    } finally {
        loading.value = false;
    }
}

async function upload(fileList) {
    await Promise.all([...fileList].map(async (file) => {
        const entry = reactive({ id: crypto.randomUUID(), name: file.name, progress: 0, error: null });
        uploads.value.push(entry);

        try {
            const stored = await uploadToWasabi(file, {
                onProgress: (percent) => (entry.progress = percent),
            });
            files.value.unshift(stored);
            emit('uploaded', stored);
            uploads.value = uploads.value.filter((u) => u.id !== entry.id);
        } catch (error) {
            entry.error = error.message;
        }
    }));
}

async function remove(file) {
    if (!confirm(`Delete ${file.name}?`)) return;
    await deleteWasabiFile(file.key);
    files.value = files.value.filter((f) => f.key !== file.key);
}

function onDrop(event) {
    dragging.value = false;
    upload(event.dataTransfer.files);
}

onMounted(refresh);
</script>

<template>
    <div class="wasabi">
        <label
            class="dropzone"
            :class="{ dragging }"
            @dragover.prevent="dragging = true"
            @dragleave.prevent="dragging = false"
            @drop.prevent="onDrop"
        >
            <input type="file" multiple :accept="accept" hidden @change="upload($event.target.files); $event.target.value = ''">
            Drop files here or <u>browse</u>
        </label>

        <ul v-if="uploads.length" class="uploads">
            <li v-for="u in uploads" :key="u.id">
                {{ u.name }}
                <span v-if="u.error" class="error">{{ u.error }}</span>
                <progress v-else :value="u.progress" max="100" />
            </li>
        </ul>

        <p v-if="loading">Loading…</p>
        <p v-else-if="!files.length">No files yet.</p>

        <ul v-else class="files">
            <li v-for="file in files" :key="file.key">
                <img v-if="isImage(file.name)" :src="file.url" alt="" width="64" loading="lazy">
                <!-- file.url is a presigned URL (valid 15 min); refresh() renews it -->
                <a :href="file.url" target="_blank" rel="noopener">{{ file.name }}</a>
                <small>{{ formatSize(file.size) }}</small>
                <button type="button" @click="remove(file)">Delete</button>
            </li>
        </ul>
    </div>
</template>

<style scoped>
.dropzone { display: block; padding: 2rem; border: 2px dashed #9ca3af; border-radius: .5rem; text-align: center; cursor: pointer; }
.dropzone.dragging { border-color: #22c55e; background: #f0fdf4; }
.files li, .uploads li { display: flex; gap: .75rem; align-items: center; padding: .25rem 0; }
.error { color: #dc2626; }
</style>
