<script setup>
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import DocReviewLayout from '@/Layouts/DocReviewLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import { ref, computed } from 'vue';

const props = defineProps({
    documents: { type: Object, required: true },
    filters: { type: Object, default: () => ({}) },
    canManage: { type: Boolean, default: false },
});

const search = ref(props.filters.search || '');
const status = ref(props.filters.status || '');

const statusOptions = [
    { value: '', label: 'All statuses' },
    { value: 'scanned', label: 'Scanned' },
    { value: 'pending', label: 'Pending' },
    { value: 'failed', label: 'Failed' },
];

const applyFilters = () => {
    router.get(route('docs.index'), {
        search: search.value || undefined,
        status: status.value || undefined,
    }, { preserveState: true, preserveScroll: true, replace: true });
};

const formatSize = (bytes) => {
    if (!bytes) return '0 B';
    const units = ['B', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(1024));
    return `${(bytes / Math.pow(1024, i)).toFixed(1)} ${units[i]}`;
};

const formatDate = (date) => {
    return new Date(date).toLocaleString();
};

const statusBadgeClass = (status) => {
    switch (status) {
        case 'scanned': return 'bg-green-100 text-green-800 dark:bg-green-900/40 dark:text-green-200';
        case 'pending': return 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/40 dark:text-yellow-200';
        case 'failed':  return 'bg-red-100 text-red-800 dark:bg-red-900/40 dark:text-red-200';
        default:        return 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-200';
    }
};

const flagBadgeClass = (count) => count > 0
    ? 'bg-red-100 text-red-800 dark:bg-red-900/40 dark:text-red-200'
    : 'bg-green-100 text-green-800 dark:bg-green-900/40 dark:text-green-200';

const rescanForm = useForm({});
const rescanningId = ref(null);

const rescan = (doc) => {
    if (confirm(`Rescan "${doc.original_name}" with the current flag word list?`)) {
        rescanningId.value = doc.id;
        rescanForm.post(route('docs.rescan', doc.id), {
            onFinish: () => { rescanningId.value = null; },
        });
    }
};
</script>

<template>
    <Head title="Document Reviewer" />

    <DocReviewLayout>
        <div class="py-6">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
                <!-- Page header -->
                <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div class="flex items-center gap-3">
                        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                            Document Reviewer
                        </h2>
                        <span
                            v-if="documents.total"
                            class="inline-flex items-center rounded-full bg-uh-teal/10 dark:bg-gray-800 px-2.5 py-0.5 text-xs font-medium text-uh-teal dark:text-gray-200 border border-uh-teal/20 dark:border-gray-700"
                        >
                            {{ documents.total }} total
                        </span>
                    </div>
                    <PrimaryButton type="button" @click="router.visit(route('docs.create'))">
                        <i class="fa-solid fa-upload text-sm mr-1.5 -ml-0.5" aria-hidden="true"></i>
                        Upload Document
                    </PrimaryButton>
                </div>

                <!-- Search + status filters -->
                <div class="bg-white dark:bg-gray-800 p-4 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700">
                    <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-3">
                        <div class="relative flex-1 min-w-0">
                            <label for="doc-search" class="sr-only">Search documents</label>
                            <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-gray-400">
                                <i class="fa-solid fa-magnifying-glass fa-lg" aria-hidden="true"></i>
                            </span>
                            <input
                                id="doc-search"
                                v-model="search"
                                type="text"
                                placeholder="Search by name..."
                                class="w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100 shadow-sm pl-10 focus:border-indigo-500 focus:ring-indigo-500"
                                @keyup.enter="applyFilters"
                            />
                        </div>
                        <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-3 shrink-0">
                            <select
                                v-model="status"
                                aria-label="Filter by status"
                                class="w-full sm:w-auto sm:min-w-[140px] rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                @change="applyFilters"
                            >
                                <option v-for="opt in statusOptions" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
                            </select>
                            <SecondaryButton type="button" class="w-full sm:w-auto justify-center" @click="applyFilters">
                                Apply
                            </SecondaryButton>
                        </div>
                    </div>
                </div>

                <!-- Documents table (desktop) -->
                <div class="hidden sm:block bg-white dark:bg-gray-800 overflow-hidden shadow-sm rounded-lg border border-gray-200 dark:border-gray-700">
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                            <thead class="bg-gray-50 dark:bg-gray-900">
                                <tr>
                                    <th scope="col" class="px-4 py-3 text-left text-xs font-semibold text-gray-600 dark:text-gray-300 uppercase tracking-wider">Name</th>
                                    <th v-if="canManage" scope="col" class="px-4 py-3 text-left text-xs font-semibold text-gray-600 dark:text-gray-300 uppercase tracking-wider">Uploaded by</th>
                                    <th scope="col" class="px-4 py-3 text-left text-xs font-semibold text-gray-600 dark:text-gray-300 uppercase tracking-wider">Status</th>
                                    <th scope="col" class="px-4 py-3 text-left text-xs font-semibold text-gray-600 dark:text-gray-300 uppercase tracking-wider">Flag Words</th>
                                    <th scope="col" class="px-4 py-3 text-left text-xs font-semibold text-gray-600 dark:text-gray-300 uppercase tracking-wider">Size</th>
                                    <th scope="col" class="px-4 py-3 text-left text-xs font-semibold text-gray-600 dark:text-gray-300 uppercase tracking-wider">Uploaded</th>
                                    <th scope="col" class="px-4 py-3 text-right text-xs font-semibold text-gray-600 dark:text-gray-300 uppercase tracking-wider">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                                <tr
                                    v-for="doc in documents.data"
                                    :key="doc.id"
                                    class="group hover:bg-gray-50 dark:hover:bg-gray-700/50 transition-colors duration-150"
                                >
                                    <td class="px-4 py-3 text-sm font-medium text-gray-900 dark:text-gray-100">
                                        <Link :href="route('docs.show', doc.id)" class="hover:text-uh-teal dark:hover:text-uh-teal hover:underline focus:outline-none focus-visible:ring-2 focus-visible:ring-uh-teal rounded">
                                            {{ doc.original_name }}
                                        </Link>
                                    </td>
                                    <td v-if="canManage" class="px-4 py-3 text-sm text-gray-700 dark:text-gray-300">{{ doc.user?.name || '—' }}</td>
                                    <td class="px-4 py-3">
                                        <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium" :class="statusBadgeClass(doc.status)">{{ doc.status }}</span>
                                    </td>
                                    <td class="px-4 py-3">
                                        <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium" :class="flagBadgeClass(doc.flag_count)">
                                            {{ doc.flag_count }} flag word{{ doc.flag_count === 1 ? '' : 's' }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 text-sm text-gray-700 dark:text-gray-300">{{ formatSize(doc.size) }}</td>
                                    <td class="px-4 py-3 text-sm text-gray-700 dark:text-gray-300">{{ formatDate(doc.created_at) }}</td>
                                    <td class="px-4 py-3 text-right whitespace-nowrap">
                                        <button
                                            v-if="canManage"
                                            type="button"
                                            :disabled="rescanningId === doc.id"
                                            class="inline-flex items-center gap-1 text-gray-500 hover:text-indigo-600 dark:text-gray-400 dark:hover:text-indigo-400 text-sm mr-3 disabled:opacity-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 rounded transition-colors"
                                            :aria-label="`Rescan ${doc.original_name}`"
                                            @click="rescan(doc)"
                                        >
                                            <i class="fa-solid fa-arrows-rotate text-sm" :class="{ 'animate-spin': rescanningId === doc.id }" aria-hidden="true"></i>
                                        </button>
                                        <Link
                                            :href="route('docs.show', doc.id)"
                                            class="inline-flex items-center gap-1 text-gray-500 hover:text-uh-teal dark:text-gray-400 dark:hover:text-uh-teal text-sm focus:outline-none focus-visible:ring-2 focus-visible:ring-uh-teal rounded transition-colors"
                                            :aria-label="`Review ${doc.original_name}`"
                                        >
                                            <i class="fa-solid fa-eye text-sm" aria-hidden="true"></i>
                                        </Link>
                                    </td>
                                </tr>
                                <tr v-if="documents.data.length === 0">
                                    <td :colspan="canManage ? 7 : 6" class="px-4 py-12 text-center">
                                        <i class="fa-solid fa-file-lines mx-auto text-4xl text-gray-300 dark:text-gray-600" aria-hidden="true"></i>
                                        <p class="mt-3 text-sm text-gray-500 dark:text-gray-400">
                                            No documents found.
                                            <Link :href="route('docs.create')" class="text-uh-teal hover:underline">Upload one</Link>.
                                        </p>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Mobile card list -->
                <div class="sm:hidden bg-white dark:bg-gray-800 overflow-hidden shadow-sm rounded-lg border border-gray-200 dark:border-gray-700 divide-y divide-gray-200 dark:divide-gray-700">
                    <div
                        v-for="doc in documents.data"
                        :key="doc.id"
                        class="p-4 hover:bg-gray-50 dark:hover:bg-gray-700/50 transition-colors duration-150"
                    >
                        <div class="flex items-start justify-between gap-2">
                            <Link :href="route('docs.show', doc.id)" class="text-sm font-medium text-gray-900 dark:text-gray-100 hover:text-uh-teal dark:hover:text-uh-teal hover:underline truncate flex-1 focus:outline-none focus-visible:ring-2 focus-visible:ring-uh-teal rounded">
                                {{ doc.original_name }}
                            </Link>
                            <div class="flex items-center gap-2 shrink-0">
                                <button
                                    v-if="canManage"
                                    type="button"
                                    :disabled="rescanningId === doc.id"
                                    class="text-gray-500 hover:text-indigo-600 dark:text-gray-400 dark:hover:text-indigo-400 disabled:opacity-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 rounded"
                                    :aria-label="`Rescan ${doc.original_name}`"
                                    @click="rescan(doc)"
                                >
                                    <i class="fa-solid fa-arrows-rotate text-sm" :class="{ 'animate-spin': rescanningId === doc.id }" aria-hidden="true"></i>
                                </button>
                                <Link
                                    :href="route('docs.show', doc.id)"
                                    class="text-gray-500 hover:text-uh-teal dark:text-gray-400 dark:hover:text-uh-teal focus:outline-none focus-visible:ring-2 focus-visible:ring-uh-teal rounded"
                                    :aria-label="`Review ${doc.original_name}`"
                                >
                                    <i class="fa-solid fa-eye text-sm" aria-hidden="true"></i>
                                </Link>
                            </div>
                        </div>
                        <div class="mt-2 flex flex-wrap items-center gap-2">
                            <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium" :class="statusBadgeClass(doc.status)">{{ doc.status }}</span>
                            <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium" :class="flagBadgeClass(doc.flag_count)">
                                {{ doc.flag_count }} flag{{ doc.flag_count === 1 ? '' : 's' }}
                            </span>
                        </div>
                        <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">
                            {{ formatSize(doc.size) }} · {{ formatDate(doc.created_at) }}
                            <span v-if="canManage && doc.user?.name"> · {{ doc.user.name }}</span>
                        </p>
                    </div>
                    <div v-if="documents.data.length === 0" class="px-4 py-12 text-center">
                        <i class="fa-solid fa-file-lines mx-auto text-4xl text-gray-300 dark:text-gray-600" aria-hidden="true"></i>
                        <p class="mt-3 text-sm text-gray-500 dark:text-gray-400">
                            No documents found.
                            <Link :href="route('docs.create')" class="text-uh-teal hover:underline">Upload one</Link>.
                        </p>
                    </div>
                </div>

                <!-- Pagination -->
                <div v-if="documents.last_page > 1" class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div class="text-sm text-gray-600 dark:text-gray-400">
                        Page {{ documents.current_page }} of {{ documents.last_page }} ({{ documents.total }} total)
                    </div>
                    <div class="flex gap-1 overflow-x-auto pb-1">
                        <Link
                            v-for="link in documents.links"
                            :key="link.label"
                            :href="link.url || '#'"
                            v-html="link.label"
                            :class="[
                                'px-3 py-1.5 text-sm rounded-md border transition-colors duration-150',
                                link.active
                                    ? 'bg-uh-teal text-white border-uh-teal'
                                    : (link.url ? 'bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-300 border-gray-300 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-700 cursor-pointer' : 'text-gray-400 border-gray-200 dark:border-gray-700 cursor-not-allowed')
                            ]"
                        />
                    </div>
                </div>
            </div>
        </div>
    </DocReviewLayout>
</template>
