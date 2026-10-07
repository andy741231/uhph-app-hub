<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import DocReviewLayout from '@/Layouts/DocReviewLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import DangerButton from '@/Components/DangerButton.vue';
import { computed, ref, onMounted, onBeforeUnmount, watch, nextTick } from 'vue';

const props = defineProps({
    document: { type: Object, required: true },
    flaggedWords: { type: Array, default: () => [] },
    flagWordMap: { type: Object, default: () => ({}) },
    highlightPattern: { type: String, default: null },
    can: { type: Object, default: () => ({}) },
});

const deleteForm = useForm({});
const rescanForm = useForm({});
const isRescanning = ref(false);

const destroy = () => {
    if (confirm('Delete this document? This cannot be undone.')) {
        deleteForm.delete(route('docs.destroy', props.document.id));
    }
};

const rescan = () => {
    if (confirm('Rescan this document with the current flag word list?')) {
        isRescanning.value = true;
        rescanForm.post(route('docs.rescan', props.document.id), {
            onFinish: () => { isRescanning.value = false; },
        });
    }
};

const formatSize = (bytes) => {
    if (!bytes) return '0 B';
    const units = ['B', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(1024));
    return `${(bytes / Math.pow(1024, i)).toFixed(1)} ${units[i]}`;
};

const formatDate = (date) => new Date(date).toLocaleString();

const hasFlags = computed(() => props.document.flag_count > 0);

// --- Document type detection ---
// When a Word document has been converted to a PDF preview, we render it
// through the PDF viewer so pagination matches the original document.
const hasPdfPreview = computed(() => !!props.document.pdf_preview_path);

const docType = computed(() => {
    const mime = (props.document.mime_type || '').toLowerCase();
    const ext = (props.document.original_name || '').split('.').pop().toLowerCase();
    if (ext === 'pdf' || mime.includes('pdf')) return 'pdf';
    if (ext === 'docx' || mime.includes('wordprocessingml')) return 'docx';
    if (ext === 'doc' || mime.includes('msword')) return 'doc';
    return 'txt';
});

// The effective type used for rendering: Word docs with a PDF preview
// are rendered through the PDF viewer.
const renderType = computed(() => {
    if ((docType.value === 'docx' || docType.value === 'doc') && hasPdfPreview.value) {
        return 'pdf';
    }
    return docType.value;
});

// --- Flagged pages from backend ---
const flaggedPages = computed(() => props.document.flagged_pages || []);

// ===================== PDF VIEWER STATE =====================
const isLoading = ref(false);
const renderError = ref(null);
const pdfScrollContainer = ref(null);   // the scrollable div
const pdfViewerContainer = ref(null);    // inner div holding page elements
const currentPage = ref(1);
const totalPdfPages = ref(0);
const pageRefs = ref([]);

// Zoom state
const pdfScale = ref(1.5);
const MIN_SCALE = 0.25;
const MAX_SCALE = 4.0;
const zoomPercent = computed(() => Math.round(pdfScale.value * 100));

// Search state
const searchQuery = ref('');
const searchMatches = ref([]);      // array of { page, affectedSpans, ... }
const currentMatchIndex = ref(-1);  // 0-based into searchMatches
const isSearching = ref(false);

// Store raw text per span per page so we can rebuild HTML with highlights
// { pageNum: [{ div, rawText }, ...] }
const pageSpanData = ref({});

// ===================== HIGHLIGHT HELPERS =====================

// Escape text for safe insertion into innerHTML
const escapeHtml = (text) => {
    return text
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;');
};

// Find all flag-word match ranges in a raw text string
const findFlagRanges = (rawText) => {
    const ranges = [];
    if (!props.highlightPattern) return ranges;
    try {
        let raw = props.highlightPattern;
        let flags = 'gi';
        const delimMatch = raw.match(/^\/(.+)\/([a-z]*)$/);
        if (delimMatch) {
            raw = delimMatch[1];
            const phpFlags = delimMatch[2].replace(/u/g, '');
            flags = phpFlags.includes('i') ? 'gi' : 'g' + phpFlags;
        }
        const regex = new RegExp(raw, flags);
        let m;
        while ((m = regex.exec(rawText)) !== null) {
            if (m[0].length === 0) { regex.lastIndex++; continue; }
            ranges.push({ start: m.index, end: m.index + m[0].length, type: 'flag' });
        }
    } catch (e) {}
    return ranges;
};

// Build span innerHTML applying both flag-word and search highlights.
// searchRanges: [{ start, end, active }] — character offsets within rawText
const buildSpanInnerHtml = (rawText, searchRanges) => {
    const flagRanges = findFlagRanges(rawText);
    const allRanges = [
        ...flagRanges,
        ...searchRanges.map(r => ({ start: r.start, end: r.end, type: r.active ? 'search-active' : 'search' })),
    ].sort((a, b) => a.start - b.start);

    let html = '';
    let pos = 0;
    for (const range of allRanges) {
        if (range.start < pos) continue; // skip overlaps
        if (range.start > pos) {
            html += escapeHtml(rawText.slice(pos, range.start));
        }
        const text = escapeHtml(rawText.slice(range.start, range.end));
        if (range.type === 'flag') {
            html += `<mark class="doc-flag-highlight">${text}</mark>`;
        } else if (range.type === 'search-active') {
            html += `<mark class="search-highlight search-active-highlight">${text}</mark>`;
        } else {
            html += `<mark class="search-highlight">${text}</mark>`;
        }
        pos = Math.max(pos, range.end);
    }
    if (pos < rawText.length) {
        html += escapeHtml(rawText.slice(pos));
    }
    return html;
};
// Plain-text fallback: split the extracted text into safe segments so the
// template can render them without v-html.
const textSegments = computed(() => {
    const text = props.document.extracted_text || '';
    if (!text) return [];
    const ranges = findFlagRanges(text);
    const segments = [];
    let pos = 0;
    for (const range of ranges) {
        if (range.start < pos) continue;
        if (range.start > pos) {
            segments.push({ text: text.slice(pos, range.start), flag: false });
        }
        segments.push({ text: text.slice(range.start, range.end), flag: true });
        pos = range.end;
    }
    if (pos < text.length) {
        segments.push({ text: text.slice(pos), flag: false });
    }
    return segments;
});

// ===================== PDF RENDERING =====================
let pdfDocRef = null;
let pdfjsLibRef = null;
let renderTaskQueue = [];

const renderPdf = async () => {
    const pdfjsLib = await import('pdfjs-dist');
    pdfjsLibRef = pdfjsLib;
    const workerUrl = (await import('pdfjs-dist/build/pdf.worker.min.mjs?url')).default;
    pdfjsLib.GlobalWorkerOptions.workerSrc = workerUrl;

    const response = await fetch(
        hasPdfPreview.value
            ? route('docs.pdf-preview', props.document.id)
            : route('docs.download', props.document.id)
    );
    if (!response.ok) throw new Error('Failed to download PDF file.');
    const arrayBuffer = await response.arrayBuffer();

    pdfDocRef = await pdfjsLib.getDocument({ data: arrayBuffer }).promise;
    totalPdfPages.value = pdfDocRef.numPages;

    await renderAllPages();
};

const renderAllPages = async () => {
    const container = pdfViewerContainer.value;
    if (!container || !pdfDocRef) return;
    container.innerHTML = '';
    pageRefs.value = [];
    pageSpanData.value = {};

    for (let pageNum = 1; pageNum <= pdfDocRef.numPages; pageNum++) {
        await renderSinglePage(pageNum);
    }

    setupScrollTracking();
    // Re-apply search if active
    if (searchQuery.value) {
        await performSearch();
    }
};

const renderSinglePage = async (pageNum) => {
    const container = pdfViewerContainer.value;
    if (!container || !pdfDocRef) return;

    const page = await pdfDocRef.getPage(pageNum);
    const viewport = page.getViewport({ scale: pdfScale.value });

    const pageDiv = document.createElement('div');
    pageDiv.className = 'pdf-page-wrapper relative mx-auto mb-6 shadow-md bg-white';
    pageDiv.style.width = `${viewport.width}px`;
    pageDiv.dataset.pageNum = String(pageNum);

    // Canvas
    const canvas = document.createElement('canvas');
    canvas.width = viewport.width;
    canvas.height = viewport.height;
    canvas.className = 'block rounded';
    pageDiv.appendChild(canvas);

    const ctx = canvas.getContext('2d');
    await page.render({ canvasContext: ctx, viewport }).promise;

    // Text layer — use PDF.js built-in TextLayer for accurate positioning
    const textContent = await page.getTextContent();
    const textLayerDiv = document.createElement('div');
    textLayerDiv.className = 'pdf-text-layer absolute inset-0';
    textLayerDiv.style.width = `${viewport.width}px`;
    textLayerDiv.style.height = `${viewport.height}px`;
    textLayerDiv.style.setProperty('--scale-factor', pdfScale.value);

    pageDiv.appendChild(textLayerDiv);
    container.appendChild(pageDiv);

    const textLayer = new pdfjsLibRef.TextLayer({
        textContentSource: textContent,
        container: textLayerDiv,
        viewport: viewport,
    });
    await textLayer.render();

    // Post-process: apply flag-word highlights to the rendered text spans
    const textDivs = textLayer.textDivs;
    const textItems = textLayer.textContentItemsStr;
    pageSpanData.value[pageNum] = [];
    if (textDivs && textItems) {
        for (let i = 0; i < textDivs.length; i++) {
            const div = textDivs[i];
            const rawText = textItems[i] || '';
            pageSpanData.value[pageNum].push({ div, rawText });
            if (rawText && props.highlightPattern) {
                div.innerHTML = buildSpanInnerHtml(rawText, []);
            }
        }
    }

    pageRefs.value[pageNum - 1] = pageDiv;
};

// ===================== ZOOM =====================
const zoomIn = () => {
    if (pdfScale.value < MAX_SCALE) {
        pdfScale.value = Math.min(MAX_SCALE, pdfScale.value + 0.25);
    }
};

const zoomOut = () => {
    if (pdfScale.value > MIN_SCALE) {
        pdfScale.value = Math.max(MIN_SCALE, pdfScale.value - 0.25);
    }
};

const fitWidth = async () => {
    if (!pdfDocRef || !pdfScrollContainer.value) return;
    const page = await pdfDocRef.getPage(1);
    const viewport1 = page.getViewport({ scale: 1 });
    const containerWidth = pdfScrollContainer.value.clientWidth - 48; // padding
    pdfScale.value = Math.max(MIN_SCALE, Math.min(MAX_SCALE, containerWidth / viewport1.width));
};

// Re-render on zoom change
let zoomTimer = null;
watch(pdfScale, () => {
    if (!pdfDocRef) return;
    clearTimeout(zoomTimer);
    zoomTimer = setTimeout(async () => {
        const el = pdfScrollContainer.value;
        const scrollRatio = el ? el.scrollTop / (el.scrollHeight || 1) : 0;
        await renderAllPages();
        // Update scroll container height to match new page size
        await nextTick();
        const firstPageEl = pdfViewerContainer.value?.querySelector('.pdf-page-wrapper');
        if (firstPageEl && el) {
            el.style.height = `${firstPageEl.offsetHeight + 48}px`;
        }
        if (el) {
            el.scrollTop = scrollRatio * el.scrollHeight;
        }
    }, 200);
});

// ===================== PAGE NAVIGATION =====================
const goToPage = (pageNum) => {
    const target = Math.max(1, Math.min(totalPdfPages.value, pageNum));
    const el = pageRefs.value[target - 1];
    if (el) {
        el.scrollIntoView({ behavior: 'smooth', block: 'start' });
        currentPage.value = target;
    }
};

// ===================== FLAG WORD HIGHLIGHT =====================
let flagWordPulseTimer = null;
const goToFlagWord = (pageNum, word) => {
    goToPage(pageNum);

    // Clear any previous pulse
    if (flagWordPulseTimer) {
        clearTimeout(flagWordPulseTimer);
    }
    document.querySelectorAll('.flag-word-pulse').forEach(el => el.classList.remove('flag-word-pulse'));

    // Find matching <mark> elements on the target page
    // Use DOM query as fallback in case pageRefs has gaps
    const cont = pdfViewerContainer.value;
    const pageDiv = pageRefs.value[pageNum - 1] ||
        (cont ? cont.querySelector(`.pdf-page-wrapper[data-page-num="${pageNum}"]`) : null);
    if (!pageDiv) return;
    const textLayer = pageDiv.querySelector('.pdf-text-layer');
    if (!textLayer) return;

    const lowerWord = word.toLowerCase();
    const marks = textLayer.querySelectorAll('mark.doc-flag-highlight');
    let firstMatch = null;
    let matchCount = 0;
    marks.forEach(mark => {
        if (mark.textContent.toLowerCase().includes(lowerWord)) {
            mark.classList.add('flag-word-pulse');
            matchCount++;
            if (!firstMatch) firstMatch = mark;
        }
    });

    // Scroll the first match into view within the scroll container
    if (firstMatch) {
        firstMatch.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }

    // Remove pulse after 5 seconds
    flagWordPulseTimer = setTimeout(() => {
        document.querySelectorAll('.flag-word-pulse').forEach(el => el.classList.remove('flag-word-pulse'));
    }, 5000);
};

const prevPage = () => goToPage(currentPage.value - 1);
const nextPage = () => goToPage(currentPage.value + 1);

const onPageInput = (e) => {
    const val = parseInt(e.target.value, 10);
    if (!isNaN(val)) goToPage(val);
};

// ===================== SCROLL TRACKING =====================
const setupScrollTracking = () => {
    const el = pdfScrollContainer.value;
    if (el) {
        el.addEventListener('scroll', updateCurrentPage);
    }
};

const updateCurrentPage = () => {
    const el = pdfScrollContainer.value;
    if (!pageRefs.value.length || !el) return;
    const containerTop = el.getBoundingClientRect().top;
    for (let i = 0; i < pageRefs.value.length; i++) {
        const rect = pageRefs.value[i].getBoundingClientRect();
        if (rect.top >= containerTop - 50 || i === pageRefs.value.length - 1) {
            currentPage.value = i + 1;
            break;
        }
    }
};

// ===================== SEARCH =====================
const performSearch = async () => {
    // Clear previous search highlights
    clearSearchHighlights();

    if (!searchQuery.value.trim() || !pdfDocRef) {
        searchMatches.value = [];
        currentMatchIndex.value = -1;
        return;
    }

    isSearching.value = true;
    const query = searchQuery.value.trim();
    const lowerQuery = query.toLowerCase();
    const matches = [];

    for (let pageNum = 1; pageNum <= pdfDocRef.numPages; pageNum++) {
        const spans = pageSpanData.value[pageNum];
        if (!spans || !spans.length) continue;

        // Build a combined text string from all spans on this page so that
        // queries spanning multiple PDF text items (e.g. words split by
        // kerning, or phrases across line breaks) can be found.
        let combinedText = '';
        const spanOffsets = []; // [{ start, end, spanIndex }]
        for (let i = 0; i < spans.length; i++) {
            const text = spans[i].rawText;
            spanOffsets.push({ start: combinedText.length, end: combinedText.length + text.length, spanIndex: i });
            combinedText += text;
        }

        const lowerCombined = combinedText.toLowerCase();
        let searchPos = 0;
        while (true) {
            const idx = lowerCombined.indexOf(lowerQuery, searchPos);
            if (idx === -1) break;
            const matchEnd = idx + query.length;

            // Map the global match range back to individual spans
            const affectedSpans = [];
            for (const off of spanOffsets) {
                if (off.end <= idx) continue;
                if (off.start >= matchEnd) break;
                const localStart = Math.max(0, idx - off.start);
                const localEnd = Math.min(off.end - off.start, matchEnd - off.start);
                affectedSpans.push({ spanIndex: off.spanIndex, localStart, localEnd });
            }

            matches.push({ page: pageNum, affectedSpans });
            searchPos = matchEnd;
        }
    }

    searchMatches.value = matches;

    // Apply highlights
    applySearchHighlights();

    if (matches.length > 0) {
        currentMatchIndex.value = 0;
        await nextTick();
        scrollToMatch(0);
    } else {
        currentMatchIndex.value = -1;
    }

    isSearching.value = false;
};

const clearSearchHighlights = () => {
    // Rebuild all spans with only flag highlights (no search highlights)
    for (const pageNum of Object.keys(pageSpanData.value)) {
        const spans = pageSpanData.value[pageNum];
        if (!spans) continue;
        for (const { div, rawText } of spans) {
            if (div && rawText) {
                div.innerHTML = buildSpanInnerHtml(rawText, []);
            }
        }
    }
};

const applySearchHighlights = () => {
    // Group search ranges by page and span so we can rebuild each affected
    // span's innerHTML with both flag-word and search highlights.
    const spanSearchRanges = {}; // { pageNum: { spanIndex: [{ start, end, active }] } }

    for (let i = 0; i < searchMatches.value.length; i++) {
        const match = searchMatches.value[i];
        const active = i === currentMatchIndex.value;
        if (!spanSearchRanges[match.page]) spanSearchRanges[match.page] = {};
        for (const affected of match.affectedSpans) {
            if (!spanSearchRanges[match.page][affected.spanIndex]) {
                spanSearchRanges[match.page][affected.spanIndex] = [];
            }
            spanSearchRanges[match.page][affected.spanIndex].push({
                start: affected.localStart,
                end: affected.localEnd,
                active,
            });
        }
    }

    for (const [pageNumStr, spanMap] of Object.entries(spanSearchRanges)) {
        const pageNum = parseInt(pageNumStr);
        const spans = pageSpanData.value[pageNum];
        if (!spans) continue;
        for (const [spanIndexStr, ranges] of Object.entries(spanMap)) {
            const spanIndex = parseInt(spanIndexStr);
            const { div, rawText } = spans[spanIndex];
            if (div && rawText) {
                div.innerHTML = buildSpanInnerHtml(rawText, ranges);
            }
        }
    }
};

const scrollToMatch = (index) => {
    if (index < 0 || index >= searchMatches.value.length) return;
    const match = searchMatches.value[index];
    const pageDiv = pageRefs.value[match.page - 1];
    if (!pageDiv) return;

    // Re-apply highlights so the active match gets the active styling
    applySearchHighlights();

    // Scroll to the actual <mark> element for this match, not just the page
    currentPage.value = match.page;
    nextTick(() => {
        const activeMark = pageDiv.querySelector('mark.search-active-highlight');
        if (activeMark) {
            activeMark.scrollIntoView({ behavior: 'smooth', block: 'center' });
        } else {
            pageDiv.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }
    });
};

const nextMatch = () => {
    if (searchMatches.value.length === 0) return;
    currentMatchIndex.value = (currentMatchIndex.value + 1) % searchMatches.value.length;
    scrollToMatch(currentMatchIndex.value);
};

const prevMatch = () => {
    if (searchMatches.value.length === 0) return;
    currentMatchIndex.value = (currentMatchIndex.value - 1 + searchMatches.value.length) % searchMatches.value.length;
    scrollToMatch(currentMatchIndex.value);
};

const onSearchInput = () => {
    // Debounce search
    clearTimeout(searchTimer);
    searchTimer = setTimeout(() => performSearch(), 300);
};

let searchTimer = null;

const clearSearch = () => {
    searchQuery.value = '';
    clearSearchHighlights();
    searchMatches.value = [];
    currentMatchIndex.value = -1;
};

// ===================== MAIN RENDER DISPATCHER =====================
const renderDocument = async () => {
    isLoading.value = true;
    renderError.value = null;
    try {
        if (renderType.value === 'pdf') {
            await renderPdf();
        }
    } catch (e) {
        renderError.value = e.message || 'Failed to render document.';
        console.error('Document render error:', e);
    } finally {
        isLoading.value = false;
        // Set scroll container height to match a single PDF page
        if (renderType.value === 'pdf' && !renderError.value) {
            await nextTick();
            const firstPageEl = pdfViewerContainer.value?.querySelector('.pdf-page-wrapper');
            const scrollEl = pdfScrollContainer.value;
            if (firstPageEl && scrollEl) {
                scrollEl.style.height = `${firstPageEl.offsetHeight + 48}px`;
            }
        }
    }
};

onMounted(() => {
    if (renderType.value === 'pdf') {
        renderDocument();
    }
});

onBeforeUnmount(() => {
    const el = pdfScrollContainer.value;
    if (el) {
        el.removeEventListener('scroll', updateCurrentPage);
    }
    if (pdfDocRef) {
        try { pdfDocRef.destroy(); } catch (e) {}
    }
});

const statusBadgeClass = (status) => {
    switch (status) {
        case 'scanned': return 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200';
        case 'pending': return 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200';
        case 'failed':  return 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200';
        default:        return 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-200';
    }
};

// ===================== WORD REPORT =====================
const wordReportEntries = computed(() => {
    const entries = [];
    for (const pageData of flaggedPages.value) {
        for (const w of pageData.words) {
            entries.push({ word: w.word, occurrences: w.occurrences, page: pageData.page });
        }
    }
    entries.sort((a, b) => a.word.localeCompare(b.word) || a.page - b.page);
    return entries;
});

const wordReportGrouped = computed(() => {
    const map = new Map();
    for (const pageData of flaggedPages.value) {
        for (const w of pageData.words) {
            if (!map.has(w.word)) {
                map.set(w.word, { word: w.word, total: 0, pages: [], replacement: replacementFor(w.word) });
            }
            const entry = map.get(w.word);
            entry.total += w.occurrences;
            entry.pages.push({ page: pageData.page, occurrences: w.occurrences });
        }
    }
    return Array.from(map.values()).sort((a, b) => b.total - a.total || a.word.localeCompare(b.word));
});

// Look up the suggested replacement for a flag word from the server-provided map
const replacementFor = (word) => props.flagWordMap[word] || '';
</script>

<template>
    <Head :title="`Review: ${document.original_name}`" />

    <DocReviewLayout>
        <div class="py-6">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
                <!-- Page header -->
                <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div class="flex items-center gap-3 min-w-0">
                        <Link
                            :href="route('docs.index')"
                            class="text-gray-400 hover:text-gray-600 dark:text-gray-500 dark:hover:text-gray-300 shrink-0 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 rounded transition-colors"
                            aria-label="Back to documents"
                        >
                            <i class="fa-solid fa-chevron-left fa-lg" aria-hidden="true"></i>
                        </Link>
                        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight truncate">
                            {{ document.original_name }}
                        </h2>
                    </div>
                    <div class="flex items-center gap-2 shrink-0">
                        <PrimaryButton
                            v-if="can.update"
                            type="button"
                            :disabled="isRescanning"
                            @click="rescan"
                        >
                            <i class="fa-solid fa-arrows-rotate text-sm mr-1.5 -ml-0.5" :class="{ 'animate-spin': isRescanning }" aria-hidden="true"></i>
                            {{ isRescanning ? 'Scanning…' : 'Rescan' }}
                        </PrimaryButton>
                        <DangerButton
                            v-if="can.delete"
                            type="button"
                            @click="destroy"
                        >
                            <i class="fa-solid fa-trash-can text-sm mr-1.5 -ml-0.5" aria-hidden="true"></i>
                            Delete
                        </DangerButton>
                    </div>
                </div>
                <!-- Top summary bar (compact) -->
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg border border-gray-200 dark:border-gray-700">
                    <div class="p-4 flex flex-wrap items-center gap-6">
                        <div>
                            <span class="text-xs uppercase text-gray-500 dark:text-gray-400">Status</span>
                            <span class="ml-2 px-2 py-0.5 text-xs rounded-full" :class="statusBadgeClass(document.status)">{{ document.status }}</span>
                        </div>
                        <div>
                            <span class="text-xs uppercase text-gray-500 dark:text-gray-400">Flag Words</span>
                            <span class="ml-2 text-lg font-semibold" :class="hasFlags ? 'text-red-600 dark:text-red-400' : 'text-green-600 dark:text-green-400'">{{ document.flag_count }}</span>
                        </div>
                        <div>
                            <span class="text-xs uppercase text-gray-500 dark:text-gray-400">Size</span>
                            <span class="ml-2 text-sm text-gray-700 dark:text-gray-300">{{ formatSize(document.size) }}</span>
                        </div>
                        <div>
                            <span class="text-xs uppercase text-gray-500 dark:text-gray-400">Uploaded</span>
                            <span class="ml-2 text-sm text-gray-700 dark:text-gray-300">{{ formatDate(document.created_at) }}</span>
                        </div>
                        <div v-if="renderType === 'pdf' && totalPdfPages > 0" class="ml-auto">
                            <span class="text-xs uppercase text-gray-500 dark:text-gray-400">Page</span>
                            <span class="ml-2 text-sm font-medium text-gray-700 dark:text-gray-300">{{ currentPage }} / {{ totalPdfPages }}</span>
                        </div>
                    </div>

                    <!-- Verdict banner -->
                    <div class="px-4 pb-4">
                        <div
                            class="rounded-md p-3"
                            :class="hasFlags
                                ? 'bg-red-50 dark:bg-red-900/30 border border-red-200 dark:border-red-800'
                                : 'bg-green-50 dark:bg-green-900/30 border border-green-200 dark:border-green-800'"
                        >
                            <p v-if="hasFlags" class="text-sm text-red-800 dark:text-red-200">
                                <strong>{{ document.flag_count }}</strong> flag word occurrence{{ document.flag_count === 1 ? '' : 's' }} found across {{ flaggedPages.length }} page{{ flaggedPages.length === 1 ? '' : 's' }}.
                            </p>
                            <p v-else class="text-sm text-green-800 dark:text-green-200">
                                No flag words found in this document.
                            </p>
                        </div>
                    </div>

                    <p v-if="document.status === 'failed' && document.error" class="px-4 pb-4 text-sm text-red-700 dark:text-red-300">
                        Extraction error: {{ document.error }}
                    </p>
                </div>

                <!-- ============ PDF LAYOUT: Viewer + Right Panel ============ -->
                <template v-if="renderType === 'pdf'">
                    <div class="flex flex-col gap-4 lg:flex-row">
                        <!-- PDF Viewer (left, takes most space) -->
                        <div class="flex-1 min-w-0">
                            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg border border-gray-200 dark:border-gray-700 flex flex-col">
                                <!-- Toolbar -->
                                <div class="px-3 py-2 border-b border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-900 flex flex-wrap items-center gap-2">
                                    <!-- Page navigation -->
                                    <div class="flex items-center gap-1">
                                        <button
                                            type="button"
                                            class="p-1.5 rounded text-gray-600 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-700 disabled:opacity-30 disabled:cursor-not-allowed"
                                            :disabled="currentPage <= 1"
                                            @click="prevPage"
                                            title="Previous page"
                                        >
                                            <i class="fa-solid fa-chevron-left text-sm" aria-hidden="true"></i>
                                        </button>
                                        <input
                                            type="number"
                                            min="1"
                                            :max="totalPdfPages"
                                            :value="currentPage"
                                            class="w-12 text-center text-xs rounded border-gray-300 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100 py-1"
                                            @change="onPageInput"
                                        />
                                        <span class="text-xs text-gray-500 dark:text-gray-400">/ {{ totalPdfPages }}</span>
                                        <button
                                            type="button"
                                            class="p-1.5 rounded text-gray-600 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-700 disabled:opacity-30 disabled:cursor-not-allowed"
                                            :disabled="currentPage >= totalPdfPages"
                                            @click="nextPage"
                                            title="Next page"
                                        >
<i class="fa-solid fa-chevron-right text-sm" aria-hidden="true"></i>
                                        </button>
                                    </div>

                                    <div class="w-px h-6 bg-gray-300 dark:bg-gray-600"></div>

                                    <!-- Zoom controls -->
                                    <div class="flex items-center gap-1">
                                        <button
                                            type="button"
                                            class="p-1.5 rounded text-gray-600 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-700 disabled:opacity-30"
                                            :disabled="pdfScale <= MIN_SCALE"
                                            @click="zoomOut"
                                            title="Zoom out"
                                        >
<i class="fa-solid fa-minus text-sm" aria-hidden="true"></i>
                                        </button>
                                        <span class="text-xs text-gray-600 dark:text-gray-300 w-12 text-center tabular-nums">{{ zoomPercent }}%</span>
                                        <button
                                            type="button"
                                            class="p-1.5 rounded text-gray-600 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-700 disabled:opacity-30"
                                            :disabled="pdfScale >= MAX_SCALE"
                                            @click="zoomIn"
                                            title="Zoom in"
                                        >
<i class="fa-solid fa-plus text-sm" aria-hidden="true"></i>
                                        </button>
                                        <button
                                            type="button"
                                            class="p-1.5 rounded text-gray-600 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-700"
                                            @click="fitWidth"
                                            title="Fit width"
                                        >
                                            <i class="fa-solid fa-expand text-sm" aria-hidden="true"></i>
                                        </button>
                                    </div>

                                    <div class="w-px h-6 bg-gray-300 dark:bg-gray-600"></div>

                                    <!-- Search -->
                                    <div class="flex items-center gap-1 flex-1 min-w-[200px]">
                                        <div class="relative flex-1">
                                            <input
                                                v-model="searchQuery"
                                                type="text"
                                                placeholder="Search in document..."
                                                class="w-full text-xs rounded border-gray-300 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100 py-1 pl-7 pr-2"
                                                @input="onSearchInput"
                                                @keyup.enter="performSearch"
                                            />
                                            <i class="fa-solid fa-magnifying-glass absolute left-2 top-1.5 text-xs text-gray-400" aria-hidden="true"></i>
                                            <button
                                                v-if="searchQuery"
                                                type="button"
                                                class="absolute right-1 top-1 inline-flex items-center justify-center w-4 h-4 text-gray-400 hover:text-gray-600"
                                                @click="clearSearch"
                                            >
                                                <i class="fa-solid fa-xmark text-sm" aria-hidden="true"></i>
                                            </button>
                                        </div>
                                        <button
                                            v-if="searchMatches.length > 0"
                                            type="button"
                                            class="p-1 rounded text-gray-600 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-700"
                                            @click="prevMatch"
                                            title="Previous match"
                                        >
                                            <i class="fa-solid fa-chevron-up text-xs" aria-hidden="true"></i>
                                        </button>
                                        <span v-if="searchMatches.length > 0" class="text-xs text-gray-500 dark:text-gray-400 tabular-nums whitespace-nowrap">
                                            {{ currentMatchIndex + 1 }}/{{ searchMatches.length }}
                                        </span>
                                        <button
                                            v-if="searchMatches.length > 0"
                                            type="button"
                                            class="p-1 rounded text-gray-600 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-700"
                                            @click="nextMatch"
                                            title="Next match"
                                        >
                                            <i class="fa-solid fa-chevron-down text-xs" aria-hidden="true"></i>
                                        </button>
                                        <span v-if="searchQuery && searchMatches.length === 0 && !isSearching" class="text-xs text-gray-400">0 results</span>
                                    </div>
                                </div>

                                <!-- PDF scrollable area -->
                                <div
                                    ref="pdfScrollContainer"
                                    class="overflow-auto bg-gray-200 dark:bg-gray-900 p-6"
                                    style="height: calc(100vh - 200px);"
                                >
                                    <div v-if="isLoading" class="flex items-center justify-center h-full">
                                        <i class="fa-solid fa-spinner fa-spin fa-2x text-indigo-500" aria-hidden="true"></i>
                                    </div>
                                    <div v-else-if="renderError" class="py-8 text-center">
                                        <p class="text-sm text-red-600 dark:text-red-400 mb-2">{{ renderError }}</p>
                                    </div>
                                    <div
                                        v-show="!isLoading && !renderError"
                                        ref="pdfViewerContainer"
                                        class="doc-viewer-container max-w-none"
                                    ></div>
                                </div>
                            </div>
                        </div>

                        <!-- Right Panel: Flag Words by Page — stacks below
                             the viewer on narrow screens, fixed rail at lg+ -->
                        <div class="w-full lg:w-72 lg:flex-shrink-0">
                            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg border border-gray-200 dark:border-gray-700 sticky top-4" style="max-height: calc(100vh - 200px);">
                                <div class="px-4 py-3 border-b border-gray-200 dark:border-gray-700">
                                    <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-200">Flag Words by Page</h3>
                                </div>
                                <div class="p-4 overflow-y-auto" style="max-height: calc(100vh - 260px);">
                                    <div v-if="flaggedPages.length === 0" class="text-sm text-gray-500 dark:text-gray-400 py-4 text-center">
                                        No flag words found.
                                    </div>
                                    <div v-else class="space-y-3">
                                        <div
                                            v-for="pageData in flaggedPages"
                                            :key="pageData.page"
                                            class="rounded-md border border-gray-200 dark:border-gray-700 overflow-hidden"
                                            :class="{ 'ring-2 ring-indigo-400': currentPage === pageData.page }"
                                        >
                                            <button
                                                type="button"
                                                class="w-full px-3 py-2 bg-gray-50 dark:bg-gray-900 text-left text-xs font-semibold text-gray-700 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-800 flex items-center justify-between"
                                                @click="goToPage(pageData.page)"
                                            >
                                                <span>Page {{ pageData.page }}</span>
                                                <span class="text-red-600 dark:text-red-400">{{ pageData.words.reduce((sum, w) => sum + w.occurrences, 0) }} hit{{ pageData.words.reduce((sum, w) => sum + w.occurrences, 0) === 1 ? '' : 's' }}</span>
                                            </button>
                                            <div class="p-2 space-y-1">
                                                <div
                                                    v-for="w in pageData.words"
                                                    :key="w.word"
                                                    class="px-2 py-1 text-xs rounded hover:bg-yellow-50 dark:hover:bg-yellow-900/30"
                                                >
                                                    <button
                                                        type="button"
                                                        class="w-full flex items-center justify-between"
                                                        @click="goToFlagWord(pageData.page, w.word)"
                                                    >
                                                        <span class="font-medium text-gray-700 dark:text-gray-300">{{ w.word }}</span>
                                                        <span class="text-red-600 dark:text-red-400">&times; {{ w.occurrences }}</span>
                                                    </button>
                                                    <p
                                                        v-if="replacementFor(w.word)"
                                                        class="mt-1 text-[10px] leading-tight text-indigo-600 dark:text-indigo-400"
                                                    >
                                                        <span class="font-semibold">Suggested:</span> {{ replacementFor(w.word) }}
                                                    </p>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </template>


                <!-- ============ TXT / FALLBACK LAYOUT: Comprehensive Report ============ -->
                <template v-else>
                    <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                        <div class="px-6 py-3 border-b border-gray-200 dark:border-gray-700">
                            <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-200">Flag Word Report</h3>
                        </div>
                        <div class="p-6">
                            <!-- No flags -->
                            <div v-if="!hasFlags" class="text-center py-8">
                                <i class="fa-solid fa-circle-check mx-auto text-5xl text-green-400" aria-hidden="true"></i>
                                <p class="mt-3 text-sm text-green-700 dark:text-green-300 font-medium">No flag words found in this document.</p>
                            </div>

                            <!-- Comprehensive report table -->
                            <div v-else class="overflow-x-auto">
                                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                    <thead class="bg-gray-50 dark:bg-gray-900">
                                        <tr>
                                            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 dark:text-gray-300 uppercase">Flag Word</th>
                                            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 dark:text-gray-300 uppercase">Suggested Replacement</th>
                                            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 dark:text-gray-300 uppercase">Total Occurrences</th>
                                            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 dark:text-gray-300 uppercase">Pages</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                                        <tr v-for="entry in wordReportGrouped" :key="entry.word" class="hover:bg-gray-50 dark:hover:bg-gray-700/50">
                                            <td class="px-4 py-3 text-sm font-medium text-gray-900 dark:text-gray-100">
                                                <mark class="bg-yellow-200 dark:bg-yellow-600 dark:text-white rounded px-1">{{ entry.word }}</mark>
                                            </td>
                                            <td class="px-4 py-3 text-xs text-indigo-600 dark:text-indigo-400">
                                                {{ entry.replacement || '—' }}
                                            </td>
                                            <td class="px-4 py-3 text-sm text-red-600 dark:text-red-400 font-semibold">{{ entry.total }}</td>
                                            <td class="px-4 py-3 text-sm text-gray-700 dark:text-gray-300">
                                                <span
                                                    v-for="(p, idx) in entry.pages"
                                                    :key="idx"
                                                    class="inline-flex items-center px-2 py-0.5 mr-1 mb-1 rounded text-xs bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300"
                                                >
                                                    Page {{ p.page }} ({{ p.occurrences }}&times;)
                                                </span>
                                            </td>
                                        </tr>
                                    </tbody>
                                    <tfoot class="bg-gray-50 dark:bg-gray-900">
                                        <tr>
                                            <td class="px-4 py-3 text-sm font-bold text-gray-900 dark:text-gray-100">Total</td>
                                            <td class="px-4 py-3"></td>
                                            <td class="px-4 py-3 text-sm font-bold text-red-600 dark:text-red-400">{{ document.flag_count }}</td>
                                            <td class="px-4 py-3 text-sm text-gray-500 dark:text-gray-400">{{ flaggedPages.length }} page{{ flaggedPages.length === 1 ? '' : 's' }} with flags</td>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>
                        </div>
                    </div>

                    <!-- Extracted plain text (safely escaped, flag words marked) -->
                    <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                        <div class="px-6 py-3 border-b border-gray-200 dark:border-gray-700">
                            <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-200">Document Content</h3>
                        </div>
                        <div class="p-6">
                            <div
                                v-if="document.extracted_text"
                                class="prose dark:prose-invert max-w-none text-sm text-gray-800 dark:text-gray-200 whitespace-pre-wrap leading-relaxed"
                            ><template v-for="(seg, i) in textSegments" :key="i"><mark v-if="seg.flag" class="doc-flag-highlight">{{ seg.text }}</mark><template v-else>{{ seg.text }}</template></template></div>
                            <p v-else class="text-sm text-gray-500 dark:text-gray-400">
                                No text could be extracted from this document.
                            </p>
                        </div>
                    </div>
                </template>

                <div class="flex justify-end">
                    <Link :href="route('docs.index')" class="text-sm text-indigo-600 dark:text-indigo-400 hover:underline">
                        &larr; Back to all documents
                    </Link>
                </div>
            </div>
        </div>
    </DocReviewLayout>
</template>

<style>
.doc-flag-highlight {
    background-color: rgba(250, 204, 21, 0.5);
    color: inherit;
    border-radius: 0;
    padding: 0;
}

.pdf-text-layer {
    position: absolute;
    text-align: initial;
    inset: 0;
    overflow: clip;
    line-height: 1;
    transform-origin: 0 0;
    z-index: 0;
    user-select: text;
    pointer-events: auto;
}

.pdf-text-layer :is(span, br) {
    color: transparent;
    position: absolute;
    white-space: pre;
    cursor: text;
    transform-origin: 0% 0%;
    pointer-events: auto;
}

.pdf-text-layer .doc-flag-highlight {
    background-color: rgba(250, 204, 21, 0.6);
    color: transparent;
    border-radius: 0;
    padding: 0;
    margin: 0;
}

/* Search match highlights on PDF text layer */
.pdf-text-layer .search-highlight {
    background-color: rgba(59, 130, 246, 0.3);
    color: transparent;
    border-radius: 0;
    padding: 0;
    margin: 0;
}

.pdf-text-layer .search-active-highlight {
    background-color: rgba(59, 130, 246, 0.6);
    outline: 1px solid rgba(59, 130, 246, 0.9);
}

/* Pulsing highlight when a flag word is clicked in the right panel */
@keyframes flag-pulse {
    0%, 100% { background-color: rgba(239, 68, 68, 0.6); }
    50% { background-color: rgba(239, 68, 68, 0.9); }
}

.pdf-text-layer .flag-word-pulse {
    animation: flag-pulse 0.6s ease-in-out 5;
    background-color: rgba(239, 68, 68, 0.6);
    outline: 2px solid rgba(239, 68, 68, 0.8);
}

/* Hide number input spinner for page navigation */
input[type="number"]::-webkit-inner-spin-button,
input[type="number"]::-webkit-outer-spin-button {
    -webkit-appearance: none;
    appearance: none;
    margin: 0;
}
input[type="number"] {
    -moz-appearance: textfield;
    appearance: textfield;
}
</style>
