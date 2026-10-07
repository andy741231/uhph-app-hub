<script setup>
import { Head, Link, useForm, router } from '@inertiajs/vue3';
import DocReviewLayout from '@/Layouts/DocReviewLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import Modal from '@/Components/Modal.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import DangerButton from '@/Components/DangerButton.vue';
import Checkbox from '@/Components/Checkbox.vue';
import { ref, computed } from 'vue';

const props = defineProps({
    flagWords: { type: Object, required: true },
    filters: { type: Object, default: () => ({}) },
});

// ---- Add form (now includes suggested replacement) ----
const form = useForm({
    word: '',
    suggested_replacement: '',
});

const showAddModal = ref(false);

const openAddModal = () => {
    form.clearErrors();
    showAddModal.value = true;
};

const closeAddModal = () => {
    showAddModal.value = false;
    form.reset('word', 'suggested_replacement');
    form.clearErrors();
};

const submit = () => {
    form.post(route('docs.flag-words.store'), {
        preserveScroll: true,
        onSuccess: () => {
            form.reset('word', 'suggested_replacement');
            showAddModal.value = false;
        },
    });
};

const destroy = (id) => {
    if (confirm('Remove this flag word?')) {
        router.delete(route('docs.flag-words.destroy', id));
    }
};

// ---- Batch selection ----
const selectedIds = ref(new Set());

const toggleSelect = (id) => {
    const next = new Set(selectedIds.value);
    next.has(id) ? next.delete(id) : next.add(id);
    selectedIds.value = next;
};

const isSelected = (id) => selectedIds.value.has(id);

const pageIds = computed(() => props.flagWords.data.map((fw) => fw.id));

const allSelected = computed(
    () => pageIds.value.length > 0 && pageIds.value.every((id) => selectedIds.value.has(id)),
);

const someSelected = computed(
    () => pageIds.value.some((id) => selectedIds.value.has(id)) && !allSelected.value,
);

const toggleSelectAll = () => {
    const next = new Set(selectedIds.value);
    if (allSelected.value) {
        pageIds.value.forEach((id) => next.delete(id));
    } else {
        pageIds.value.forEach((id) => next.add(id));
    }
    selectedIds.value = next;
};

const selectedCount = computed(() => selectedIds.value.size);

const clearSelection = () => { selectedIds.value = new Set(); };

const bulkForm = useForm({ ids: [] });

const bulkDestroy = () => {
    const ids = Array.from(selectedIds.value);
    if (ids.length === 0) return;
    if (!confirm(`Remove ${ids.length} flag word${ids.length === 1 ? '' : 's'}?`)) return;
    bulkForm.ids = ids;
    bulkForm.delete(route('docs.flag-words.bulk-destroy'), {
        preserveScroll: true,
        onSuccess: () => {
            clearSelection();
            bulkForm.reset();
        },
    });
};

const formatDate = (date) => new Date(date).toLocaleString();

// ---- Search + sort ----
const search = ref(props.filters.search || '');
const sort = ref(props.filters.sort || 'word');
const direction = ref(props.filters.direction || 'asc');

const applyFilters = () => {
    router.get(route('docs.flag-words.index'), {
        search: search.value || undefined,
        sort: sort.value || undefined,
        direction: direction.value || undefined,
    }, { preserveState: true, preserveScroll: true, replace: true });
};

// Debounced search
let searchTimer = null;
const onSearchInput = () => {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(() => applyFilters(), 300);
};

// Sortable column headers
const sortableColumns = [
    { key: 'word', label: 'Word' },
    { key: 'suggested_replacement', label: 'Suggested Replacement' },
    { key: 'created_at', label: 'Added on' },
];

const ariaSort = (column) => {
    if (sort.value !== column) return 'none';
    return direction.value === 'asc' ? 'ascending' : 'descending';
};

const toggleSort = (column) => {
    if (sort.value === column) {
        direction.value = direction.value === 'asc' ? 'desc' : 'asc';
    } else {
        sort.value = column;
        direction.value = 'asc';
    }
    applyFilters();
};

const sortIconClass = (column) => {
    if (sort.value !== column) return 'fa-up-down';
    return direction.value === 'asc' ? 'fa-arrow-up' : 'fa-arrow-down';
};

// ---- Inline edit ----
const editingId = ref(null);
const editForm = useForm({
    word: '',
    suggested_replacement: '',
});

const startEdit = (fw) => {
    editingId.value = fw.id;
    editForm.word = fw.word;
    editForm.suggested_replacement = fw.suggested_replacement || '';
    editForm.clearErrors();
};

const cancelEdit = () => {
    editingId.value = null;
    editForm.reset();
    editForm.clearErrors();
};

const saveEdit = (fw) => {
    editForm.put(route('docs.flag-words.update', fw.id), {
        preserveScroll: true,
        onSuccess: () => cancelEdit(),
    });
};
</script>

<template>
    <Head title="Flag Words" />

    <DocReviewLayout>
        <div class="py-6">
            <div class="max-w-5xl mx-auto sm:px-6 lg:px-8 space-y-6">
                <!-- Page header -->
                <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div class="flex items-center gap-3">
                        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                            Flag Words
                        </h2>
                        <span
                            v-if="flagWords.total"
                            class="inline-flex items-center rounded-full bg-uh-teal/10 dark:bg-gray-800 px-2.5 py-0.5 text-xs font-medium text-uh-teal dark:text-gray-200 border border-uh-teal/20 dark:border-gray-700"
                        >
                            {{ flagWords.total }} total
                        </span>
                    </div>
                    <PrimaryButton type="button" @click="openAddModal">
                        <i class="fa-solid fa-plus text-sm mr-1.5 -ml-0.5" aria-hidden="true"></i>
                        Add Flag Word
                    </PrimaryButton>
                </div>
                <!-- Search + sort filters -->
                <div class="bg-white dark:bg-gray-800 p-4 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700">
                    <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-3">
                        <div class="relative flex-1 min-w-0">
                            <label for="flag-word-search" class="sr-only">Search flag words</label>
                            <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-gray-400">
                                <i class="fa-solid fa-magnifying-glass fa-lg" aria-hidden="true"></i>
                            </span>
                            <input
                                id="flag-word-search"
                                v-model="search"
                                type="text"
                                placeholder="Search word or replacement..."
                                class="w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100 shadow-sm pl-10 focus:border-indigo-500 focus:ring-indigo-500"
                                @input="onSearchInput"
                            />
                        </div>
                        <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-3 shrink-0">
                            <select
                                v-model="sort"
                                aria-label="Sort by"
                                class="w-full sm:w-auto sm:min-w-[140px] rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                @change="applyFilters"
                            >
                                <option value="word">Sort: Word</option>
                                <option value="suggested_replacement">Sort: Replacement</option>
                                <option value="created_at">Sort: Added on</option>
                            </select>
                            <select
                                v-model="direction"
                                aria-label="Sort direction"
                                class="w-full sm:w-auto sm:min-w-[120px] rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                @change="applyFilters"
                            >
                                <option value="asc">Ascending</option>
                                <option value="desc">Descending</option>
                            </select>
                            <SecondaryButton type="button" class="w-full sm:w-auto justify-center" @click="applyFilters">
                                Apply
                            </SecondaryButton>
                        </div>
                    </div>
                </div>

                <!-- Batch action bar -->
                <Transition
                    enter-active-class="transition duration-200 ease-out"
                    enter-from-class="opacity-0 -translate-y-1"
                    enter-to-class="opacity-100 translate-y-0"
                    leave-active-class="transition duration-150 ease-in"
                    leave-from-class="opacity-100 translate-y-0"
                    leave-to-class="opacity-0 -translate-y-1"
                >
                    <div
                        v-if="selectedCount > 0"
                        class="flex items-center justify-between bg-indigo-50 dark:bg-indigo-900/30 border border-indigo-200 dark:border-indigo-700 rounded-lg px-4 py-3"
                    >
                        <div class="text-sm font-medium text-indigo-700 dark:text-indigo-300">
                            {{ selectedCount }} selected
                        </div>
                        <div class="flex items-center gap-3">
                            <SecondaryButton type="button" @click="clearSelection">
                                Clear
                            </SecondaryButton>
                            <DangerButton
                                type="button"
                                :disabled="bulkForm.processing"
                                @click="bulkDestroy"
                            >
                                <i class="fa-solid fa-trash-can text-sm mr-1.5 -ml-0.5" aria-hidden="true"></i>
                                {{ bulkForm.processing ? 'Deleting…' : 'Delete Selected' }}
                            </DangerButton>
                        </div>
                    </div>
                </Transition>

                <!-- List (desktop table) -->
                <div class="hidden sm:block bg-white dark:bg-gray-800 overflow-hidden shadow-sm rounded-lg border border-gray-200 dark:border-gray-700">
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                            <thead class="bg-gray-50 dark:bg-gray-900 sticky top-0">
                                <tr>
                                    <th scope="col" class="px-4 py-3 w-10">
                                        <label class="inline-flex items-center">
                                            <span class="sr-only">Select all on this page</span>
                                            <Checkbox
                                                :checked="allSelected"
                                                @update:checked="toggleSelectAll"
                                            />
                                        </label>
                                    </th>
                                    <th
                                        v-for="col in sortableColumns"
                                        :key="col.key"
                                        scope="col"
                                        :aria-sort="ariaSort(col.key)"
                                        class="px-4 py-3 text-left text-xs font-semibold text-gray-600 dark:text-gray-300 uppercase tracking-wider"
                                    >
                                        <button
                                            type="button"
                                            class="inline-flex items-center gap-1 cursor-pointer select-none hover:text-gray-900 dark:hover:text-white focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 rounded -mx-0.5 px-0.5"
                                            @click="toggleSort(col.key)"
                                        >
                                            {{ col.label }}
                                            <i
                                                class="fa-solid text-xs"
                                                :class="[sortIconClass(col.key), sort === col.key ? 'text-indigo-500' : 'text-gray-400']"
                                                aria-hidden="true"
                                            ></i>
                                            <span class="sr-only">Sort by {{ col.label }}</span>
                                        </button>
                                    </th>
                                    <th scope="col" class="px-4 py-3 text-left text-xs font-semibold text-gray-600 dark:text-gray-300 uppercase tracking-wider">Added by</th>
                                    <th scope="col" class="px-4 py-3 text-right text-xs font-semibold text-gray-600 dark:text-gray-300 uppercase tracking-wider">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                                <tr
                                    v-for="fw in flagWords.data"
                                    :key="fw.id"
                                    class="group hover:bg-gray-50 dark:hover:bg-gray-700/50 transition-colors duration-150"
                                    :class="{ 'bg-indigo-50 dark:bg-indigo-900/20': isSelected(fw.id) }"
                                >
                                    <!-- Checkbox cell -->
                                    <td class="px-4 py-3 w-10">
                                        <Checkbox
                                            :checked="isSelected(fw.id)"
                                            :aria-label="`Select ${fw.word}`"
                                            @update:checked="toggleSelect(fw.id)"
                                        />
                                    </td>
                                    <!-- Edit mode -->
                                    <template v-if="editingId === fw.id">
                                        <td class="px-4 py-3">
                                            <input
                                                v-model="editForm.word"
                                                type="text"
                                                aria-label="Edit word"
                                                class="w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm"
                                            />
                                            <InputError class="mt-1" :message="editForm.errors.word" />
                                        </td>
                                        <td class="px-4 py-3">
                                            <input
                                                v-model="editForm.suggested_replacement"
                                                type="text"
                                                aria-label="Edit suggested replacement"
                                                class="w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm"
                                            />
                                            <InputError class="mt-1" :message="editForm.errors.suggested_replacement" />
                                        </td>
                                        <td class="px-4 py-3 text-sm text-gray-700 dark:text-gray-300">{{ fw.creator?.name || '—' }}</td>
                                        <td class="px-4 py-3 text-sm text-gray-700 dark:text-gray-300">{{ formatDate(fw.created_at) }}</td>
                                        <td class="px-4 py-3 text-right whitespace-nowrap">
                                            <button
                                                type="button"
                                                :disabled="editForm.processing"
                                                class="inline-flex items-center gap-1 text-green-600 dark:text-green-400 hover:text-green-700 dark:hover:text-green-300 text-sm mr-3 disabled:opacity-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-green-500 rounded transition-colors"
                                                @click="saveEdit(fw)"
                                            >
                                                <i class="fa-solid fa-check text-sm" aria-hidden="true"></i>
                                                Save
                                            </button>
                                            <button
                                                type="button"
                                                :disabled="editForm.processing"
                                                class="inline-flex items-center gap-1 text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-300 text-sm disabled:opacity-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-gray-400 rounded transition-colors"
                                                @click="cancelEdit"
                                            >
                                                <i class="fa-solid fa-xmark text-sm" aria-hidden="true"></i>
                                                Cancel
                                            </button>
                                        </td>
                                    </template>
                                    <!-- View mode -->
                                    <template v-else>
                                        <td class="px-4 py-3 text-sm font-medium text-gray-900 dark:text-gray-100">{{ fw.word }}</td>
                                        <td class="px-4 py-3 text-xs">
                                            <span
                                                v-if="fw.suggested_replacement"
                                                class="inline-flex items-center rounded-md bg-indigo-50 dark:bg-indigo-900/30 px-2 py-0.5 font-medium text-indigo-700 dark:text-indigo-300"
                                            >{{ fw.suggested_replacement }}</span>
                                            <span v-else class="text-gray-400 dark:text-gray-500">—</span>
                                        </td>
                                        <td class="px-4 py-3 text-sm text-gray-700 dark:text-gray-300">{{ fw.creator?.name || '—' }}</td>
                                        <td class="px-4 py-3 text-sm text-gray-700 dark:text-gray-300">{{ formatDate(fw.created_at) }}</td>
                                        <td class="px-4 py-3 text-right whitespace-nowrap">
                                            <button
                                                type="button"
                                                class="inline-flex items-center gap-1 text-gray-500 hover:text-indigo-600 dark:text-gray-400 dark:hover:text-indigo-400 text-sm mr-3 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 rounded transition-colors"
                                                :aria-label="`Edit ${fw.word}`"
                                                @click="startEdit(fw)"
                                            >
                                                <i class="fa-solid fa-pen-to-square text-sm" aria-hidden="true"></i>
                                            </button>
                                            <button
                                                type="button"
                                                class="inline-flex items-center gap-1 text-gray-500 hover:text-red-600 dark:text-gray-400 dark:hover:text-red-400 text-sm focus:outline-none focus-visible:ring-2 focus-visible:ring-red-500 rounded transition-colors"
                                                :aria-label="`Remove ${fw.word}`"
                                                @click="destroy(fw.id)"
                                            >
                                                <i class="fa-solid fa-trash-can text-sm" aria-hidden="true"></i>
                                            </button>
                                        </td>
                                    </template>
                                </tr>
                                <tr v-if="flagWords.data.length === 0">
                                    <td colspan="6" class="px-4 py-12 text-center">
                                        <i class="fa-solid fa-file-lines mx-auto text-4xl text-gray-300 dark:text-gray-600" aria-hidden="true"></i>
                                        <p class="mt-3 text-sm text-gray-500 dark:text-gray-400">
                                            No flag words found.
                                            <span v-if="search">Try a different search.</span>
                                            <span v-else>Add one to get started.</span>
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
                        v-for="fw in flagWords.data"
                        :key="fw.id"
                        class="p-4 hover:bg-gray-50 dark:hover:bg-gray-700/50 transition-colors duration-150"
                        :class="{ 'bg-indigo-50 dark:bg-indigo-900/20': isSelected(fw.id) }"
                    >
                        <!-- Edit mode mobile -->
                        <div v-if="editingId === fw.id" class="space-y-3">
                            <input
                                v-model="editForm.word"
                                type="text"
                                aria-label="Edit word"
                                class="w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm"
                            />
                            <InputError :message="editForm.errors.word" />
                            <input
                                v-model="editForm.suggested_replacement"
                                type="text"
                                aria-label="Edit suggested replacement"
                                class="w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm"
                            />
                            <InputError :message="editForm.errors.suggested_replacement" />
                            <div class="flex items-center justify-end gap-2">
                                <button
                                    type="button"
                                    :disabled="editForm.processing"
                                    class="inline-flex items-center gap-1 text-green-600 dark:text-green-400 text-sm disabled:opacity-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-green-500 rounded"
                                    @click="saveEdit(fw)"
                                >
                                    <i class="fa-solid fa-check text-sm" aria-hidden="true"></i>
                                    Save
                                </button>
                                <button
                                    type="button"
                                    :disabled="editForm.processing"
                                    class="inline-flex items-center gap-1 text-gray-500 dark:text-gray-400 text-sm disabled:opacity-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-gray-400 rounded"
                                    @click="cancelEdit"
                                >
                                    <i class="fa-solid fa-xmark text-sm" aria-hidden="true"></i>
                                    Cancel
                                </button>
                            </div>
                        </div>

                        <!-- View mode mobile -->
                        <div v-else class="flex items-start gap-3">
                            <Checkbox
                                :checked="isSelected(fw.id)"
                                :aria-label="`Select ${fw.word}`"
                                @update:checked="toggleSelect(fw.id)"
                            />
                            <div class="flex-1 min-w-0">
                                <div class="flex items-start justify-between gap-2">
                                    <p class="text-sm font-medium text-gray-900 dark:text-gray-100 truncate">{{ fw.word }}</p>
                                    <div class="flex items-center gap-2 shrink-0">
                                        <button
                                            type="button"
                                            class="text-gray-500 hover:text-indigo-600 dark:text-gray-400 dark:hover:text-indigo-400 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 rounded"
                                            :aria-label="`Edit ${fw.word}`"
                                            @click="startEdit(fw)"
                                        >
                                            <i class="fa-solid fa-pen-to-square text-sm" aria-hidden="true"></i>
                                        </button>
                                        <button
                                            type="button"
                                            class="text-gray-500 hover:text-red-600 dark:text-gray-400 dark:hover:text-red-400 focus:outline-none focus-visible:ring-2 focus-visible:ring-red-500 rounded"
                                            :aria-label="`Remove ${fw.word}`"
                                            @click="destroy(fw.id)"
                                        >
                                            <i class="fa-solid fa-trash-can text-sm" aria-hidden="true"></i>
                                        </button>
                                    </div>
                                </div>
                                <p v-if="fw.suggested_replacement" class="mt-1 text-xs">
                                    <span class="inline-flex items-center rounded-md bg-indigo-50 dark:bg-indigo-900/30 px-2 py-0.5 font-medium text-indigo-700 dark:text-indigo-300">
                                        {{ fw.suggested_replacement }}
                                    </span>
                                </p>
                                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                    {{ formatDate(fw.created_at) }}
                                    <span v-if="fw.creator?.name"> · {{ fw.creator.name }}</span>
                                </p>
                            </div>
                        </div>
                    </div>
                    <div v-if="flagWords.data.length === 0" class="px-4 py-12 text-center">
                        <i class="fa-solid fa-file-lines mx-auto text-4xl text-gray-300 dark:text-gray-600" aria-hidden="true"></i>
                        <p class="mt-3 text-sm text-gray-500 dark:text-gray-400">
                            No flag words found.
                            <span v-if="search">Try a different search.</span>
                            <span v-else>Add one to get started.</span>
                        </p>
                    </div>
                </div>

                <div v-if="flagWords.last_page > 1" class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div class="text-sm text-gray-600 dark:text-gray-400">
                        Page {{ flagWords.current_page }} of {{ flagWords.last_page }} ({{ flagWords.total }} total)
                    </div>
                    <div class="flex gap-1 overflow-x-auto pb-1">
                        <Link
                            v-for="link in flagWords.links"
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

        <!-- Add Flag Word Modal -->
        <Modal :show="showAddModal" max-width="lg" @close="closeAddModal">
            <div class="p-6">
                <div class="flex items-start justify-between mb-4">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100">Add Flag Word</h3>
                    <button
                        type="button"
                        class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 rounded"
                        aria-label="Close dialog"
                        @click="closeAddModal"
                    >
                        <i class="fa-solid fa-xmark fa-lg" aria-hidden="true"></i>
                    </button>
                </div>
                <form @submit.prevent="submit" class="space-y-4">
                    <div>
                        <InputLabel for="word" value="Word" />
                        <input
                            id="word"
                            v-model="form.word"
                            type="text"
                            placeholder="e.g. confidential"
                            class="mt-1 w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                            autofocus
                        />
                        <InputError class="mt-1" :message="form.errors.word" />
                    </div>
                    <div>
                        <InputLabel for="suggested_replacement" value="Suggested Replacement" />
                        <input
                            id="suggested_replacement"
                            v-model="form.suggested_replacement"
                            type="text"
                            placeholder="e.g. internal use only"
                            class="mt-1 w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                        />
                        <InputError class="mt-1" :message="form.errors.suggested_replacement" />
                    </div>
                    <p class="text-xs text-gray-500 dark:text-gray-400">
                        Matching is case-insensitive. Single words also match stems at a word boundary (e.g. "divers" matches "diversity"); multi-word phrases match literally.
                    </p>
                    <div class="flex items-center justify-end gap-3 pt-2">
                        <SecondaryButton type="button" @click="closeAddModal">
                            Cancel
                        </SecondaryButton>
                        <PrimaryButton type="submit" :class="{ 'opacity-25': form.processing }" :disabled="form.processing">
                            Add
                        </PrimaryButton>
                    </div>
                </form>
            </div>
        </Modal>
    </DocReviewLayout>
</template>
