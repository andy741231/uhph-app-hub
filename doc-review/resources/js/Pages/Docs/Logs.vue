<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import DocReviewLayout from '@/Layouts/DocReviewLayout.vue';
import { ref } from 'vue';

const props = defineProps({
    logs: { type: Object, required: true },
    filters: { type: Object, default: () => ({}) },
});

const action = ref(props.filters.action || '');

const actionOptions = [
    { value: '', label: 'All activity' },
    { value: 'login', label: 'Signed in' },
    { value: 'document_uploaded', label: 'Uploaded document' },
    { value: 'document_deleted', label: 'Deleted document' },
];

const actionLabels = Object.fromEntries(actionOptions.map((o) => [o.value, o.label]));
const actionLabel = (a) => actionLabels[a] || a;

const actionBadgeClass = (a) => {
    switch (a) {
        case 'login':             return 'bg-blue-100 text-blue-800 dark:bg-blue-900/40 dark:text-blue-200';
        case 'document_uploaded': return 'bg-green-100 text-green-800 dark:bg-green-900/40 dark:text-green-200';
        case 'document_deleted':  return 'bg-red-100 text-red-800 dark:bg-red-900/40 dark:text-red-200';
        default:                  return 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-200';
    }
};

const applyFilters = () => {
    router.get(route('docs.logs.index'), {
        action: action.value || undefined,
    }, { preserveState: true, preserveScroll: true, replace: true });
};

// Timestamps are UTC in the database; the browser renders them in the
// viewer's own time zone.
const browserTimeZone = Intl.DateTimeFormat().resolvedOptions().timeZone;
const isoDate = (date) => new Date(date).toISOString();
const formatDate = (date) => new Date(date).toLocaleString();

const focusRing = 'focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-uh-red focus-visible:ring-offset-1';

const pageButtonClass = (enabled) => [
    'px-3 py-1.5 text-sm rounded-md border transition-colors duration-150',
    focusRing,
    enabled
        ? 'bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-300 border-gray-300 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-700 cursor-pointer'
        : 'text-gray-400 border-gray-200 dark:border-gray-700 cursor-not-allowed',
];
</script>

<template>
    <Head title="Logs" />

    <DocReviewLayout>
        <div class="py-6">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
                <!-- Page header -->
                <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div class="flex items-center gap-3">
                        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                            Activity Logs
                        </h2>
                        <span
                            v-if="logs.total"
                            class="inline-flex items-center rounded-full bg-uh-teal/10 dark:bg-gray-800 px-2.5 py-0.5 text-xs font-medium text-uh-teal dark:text-gray-200 border border-uh-teal/20 dark:border-gray-700"
                        >
                            {{ logs.total }} total
                        </span>
                    </div>
                    <p class="text-xs text-gray-500 dark:text-gray-400">
                        Times shown in your browser's time zone ({{ browserTimeZone }}).
                    </p>
                </div>

                <!-- Action filter -->
                <div class="bg-white dark:bg-gray-800 p-4 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700">
                    <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-3">
                        <select
                            v-model="action"
                            aria-label="Filter by action"
                            class="w-full sm:w-auto sm:min-w-[200px] rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                            @change="applyFilters"
                        >
                            <option v-for="opt in actionOptions" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
                        </select>
                    </div>
                </div>

                <!-- Activity table (desktop) -->
                <div class="hidden sm:block bg-white dark:bg-gray-800 overflow-hidden shadow-sm rounded-lg border border-gray-200 dark:border-gray-700">
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                            <thead class="bg-gray-50 dark:bg-gray-900">
                                <tr>
                                    <th scope="col" class="px-4 py-3 text-left text-xs font-semibold text-gray-600 dark:text-gray-300 uppercase tracking-wider">User</th>
                                    <th scope="col" class="px-4 py-3 text-left text-xs font-semibold text-gray-600 dark:text-gray-300 uppercase tracking-wider">Action</th>
                                    <th scope="col" class="px-4 py-3 text-left text-xs font-semibold text-gray-600 dark:text-gray-300 uppercase tracking-wider">Document</th>
                                    <th scope="col" class="px-4 py-3 text-left text-xs font-semibold text-gray-600 dark:text-gray-300 uppercase tracking-wider">Date and time</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                                <tr
                                    v-for="log in logs.data"
                                    :key="log.id"
                                    class="hover:bg-gray-50 dark:hover:bg-gray-700/50 transition-colors duration-150"
                                >
                                    <td class="px-4 py-3 text-sm text-gray-900 dark:text-gray-100">
                                        <div class="font-medium">{{ log.actor_name }}</div>
                                        <div class="text-xs text-gray-500 dark:text-gray-400">{{ log.actor_email }}</div>
                                    </td>
                                    <td class="px-4 py-3">
                                        <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium" :class="actionBadgeClass(log.action)">
                                            {{ actionLabel(log.action) }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 text-sm text-gray-700 dark:text-gray-300">
                                        <span v-if="log.document_name">{{ log.document_name }}</span>
                                        <span v-else aria-hidden="true">&mdash;</span>
                                    </td>
                                    <td class="px-4 py-3 text-sm text-gray-700 dark:text-gray-300 whitespace-nowrap">
                                        <time :datetime="isoDate(log.created_at)">{{ formatDate(log.created_at) }}</time>
                                    </td>
                                </tr>
                                <tr v-if="logs.data.length === 0">
                                    <td colspan="4" class="px-4 py-12 text-center">
                                        <i class="fa-solid fa-clipboard-list mx-auto text-4xl text-gray-300 dark:text-gray-600" aria-hidden="true"></i>
                                        <p class="mt-3 text-sm text-gray-500 dark:text-gray-400">
                                            No activity recorded yet.
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
                        v-for="log in logs.data"
                        :key="log.id"
                        class="p-4 hover:bg-gray-50 dark:hover:bg-gray-700/50 transition-colors duration-150"
                    >
                        <div class="flex items-start justify-between gap-2">
                            <div class="min-w-0">
                                <p class="text-sm font-medium text-gray-900 dark:text-gray-100 truncate">{{ log.actor_name }}</p>
                                <p class="text-xs text-gray-500 dark:text-gray-400 truncate">{{ log.actor_email }}</p>
                            </div>
                            <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium shrink-0" :class="actionBadgeClass(log.action)">
                                {{ actionLabel(log.action) }}
                            </span>
                        </div>
                        <dl class="mt-2 space-y-1 text-xs text-gray-500 dark:text-gray-400">
                            <div class="flex gap-1">
                                <dt class="font-medium text-gray-600 dark:text-gray-300">Document:</dt>
                                <dd class="truncate">
                                    <span v-if="log.document_name">{{ log.document_name }}</span>
                                    <span v-else aria-hidden="true">&mdash;</span>
                                </dd>
                            </div>
                            <div class="flex gap-1">
                                <dt class="font-medium text-gray-600 dark:text-gray-300">When:</dt>
                                <dd><time :datetime="isoDate(log.created_at)">{{ formatDate(log.created_at) }}</time></dd>
                            </div>
                        </dl>
                    </div>
                    <div v-if="logs.data.length === 0" class="px-4 py-12 text-center">
                        <i class="fa-solid fa-clipboard-list mx-auto text-4xl text-gray-300 dark:text-gray-600" aria-hidden="true"></i>
                        <p class="mt-3 text-sm text-gray-500 dark:text-gray-400">
                            No activity recorded yet.
                        </p>
                    </div>
                </div>

                <!-- Pagination -->
                <div v-if="logs.last_page > 1" class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div class="text-sm text-gray-600 dark:text-gray-400">
                        Page {{ logs.current_page }} of {{ logs.last_page }} ({{ logs.total }} total)
                    </div>
                    <div class="flex gap-2">
                        <Link
                            v-if="logs.prev_page_url"
                            :href="logs.prev_page_url"
                            preserve-scroll
                            :class="pageButtonClass(true)"
                        >
                            Previous
                        </Link>
                        <span v-else aria-disabled="true" :class="pageButtonClass(false)">Previous</span>
                        <Link
                            v-if="logs.next_page_url"
                            :href="logs.next_page_url"
                            preserve-scroll
                            :class="pageButtonClass(true)"
                        >
                            Next
                        </Link>
                        <span v-else aria-disabled="true" :class="pageButtonClass(false)">Next</span>
                    </div>
                </div>
            </div>
        </div>
    </DocReviewLayout>
</template>
