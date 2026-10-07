<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import DocReviewLayout from '@/Layouts/DocReviewLayout.vue';
import InputError from '@/Components/InputError.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import { ref } from 'vue';

const form = useForm({
    file: null,
});

const selectedFileName = ref('');
const isDragging = ref(false);

const selectFile = (file) => {
    if (file) {
        form.file = file;
        selectedFileName.value = file.name;
    }
};

const onFileChange = (e) => {
    selectFile(e.target.files[0]);
};

const onDrop = (e) => {
    isDragging.value = false;
    const file = e.dataTransfer?.files?.[0];
    if (file) {
        selectFile(file);
    }
};

const onDragOver = () => {
    isDragging.value = true;
};

const onDragLeave = () => {
    isDragging.value = false;
};

const submit = () => {
    form.post(route('docs.store'), {
        preserveScroll: true,
    });
};
</script>

<template>
    <Head title="Upload Document" />

    <DocReviewLayout>
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                Upload Document
            </h2>
        </template>

        <div class="py-6">
            <div class="max-w-2xl mx-auto sm:px-6 lg:px-8">
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <form @submit.prevent="submit" class="space-y-6">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Document file</label>
                                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                    Supported formats: .txt, .pdf, .docx. Legacy .doc requires a LibreOffice preview/conversion service on the server. Max size: 10 MB.
                                </p>
                                <div class="mt-2 flex items-center justify-center w-full">
                                    <label
                                        for="file"
                                        class="flex flex-col items-center justify-center w-full h-32 border-2 border-dashed rounded-lg cursor-pointer bg-gray-50 dark:hover:bg-gray-700 dark:bg-gray-900 hover:bg-gray-100 dark:border-gray-600 transition-colors"
                                        :class="isDragging
                                            ? 'border-indigo-500 bg-indigo-50 dark:bg-indigo-900/30 ring-2 ring-indigo-300'
                                            : 'border-gray-300'"
                                        @dragover.prevent="onDragOver"
                                        @dragenter.prevent="onDragOver"
                                        @dragleave.prevent="onDragLeave"
                                        @drop.prevent="onDrop"
                                    >
                                        <div class="flex flex-col items-center justify-center pt-5 pb-6">
                                            <i class="fa-solid fa-cloud-arrow-up text-3xl mb-3 text-gray-400" aria-hidden="true"></i>
                                            <p class="mb-2 text-sm text-gray-500 dark:text-gray-400">
                                                <span class="font-semibold">Click to upload</span> or drag and drop
                                            </p>
                                            <p v-if="selectedFileName" class="text-xs text-indigo-600 dark:text-indigo-400">{{ selectedFileName }}</p>
                                        </div>
                                        <input id="file" type="file" class="sr-only" accept=".txt,.pdf,.docx,.doc,text/plain,application/pdf,application/vnd.openxmlformats-officedocument.wordprocessingml.document" @change="onFileChange" />
                                    </label>
                                </div>
                                <InputError class="mt-2" :message="form.errors.file" />
                            </div>

                            <div class="flex items-center justify-between pt-4 border-t border-gray-200 dark:border-gray-700">
                                <Link :href="route('docs.index')" class="inline-flex items-center px-4 py-2 bg-gray-800 dark:bg-gray-200 border border-transparent rounded-md font-semibold text-xs text-white dark:text-gray-800 uppercase tracking-widest hover:bg-gray-700 dark:hover:bg-white">
                                    Cancel
                                </Link>
                                <PrimaryButton type="submit" :class="{ 'opacity-25': form.processing }" :disabled="form.processing">
                                    Upload &amp; Review
                                </PrimaryButton>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </DocReviewLayout>
</template>
