// Pure helpers for flag-word highlighting, extracted from Docs/Show.vue so
// page-context boundary semantics are testable outside the component.

// Escape text for safe insertion into innerHTML
export const escapeHtml = (text) => {
    return String(text)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;');
};

// Compile the backend-provided "/pattern/flags" string into a RegExp.
// The PHP 'u' modifier is dropped - JS operates on UTF-16 and the '\b'
// boundary semantics we rely on are ASCII-friendly.
export const compileFlagRegex = (pattern) => {
    if (!pattern) return null;
    try {
        let raw = pattern;
        let flags = 'gi';
        const delimMatch = raw.match(/^\/(.+)\/([a-z]*)$/);
        if (delimMatch) {
            raw = delimMatch[1];
            const phpFlags = delimMatch[2].replace(/u/g, '');
            flags = phpFlags.includes('i') ? 'gi' : 'g' + phpFlags;
        }
        return new RegExp(raw, flags);
    } catch (e) {
        return null;
    }
};

// Find all flag-word match ranges in a text string.
export const findFlagRanges = (regex, text) => {
    const ranges = [];
    if (!regex || !text) return ranges;
    regex.lastIndex = 0;
    let m;
    while ((m = regex.exec(text)) !== null) {
        if (m[0].length === 0) { regex.lastIndex++; continue; }
        ranges.push({ start: m.index, end: m.index + m[0].length });
    }
    return ranges;
};

// Compute flag-word match ranges on the concatenated text of a whole PDF
// page, then map them back to per-span local offsets. A newline separator
// is appended after items flagged hasEOL so whole-word boundaries behave
// like real line breaks; within a line, adjacent items join seamlessly so
// a word split across spans ("A" + "I") still forms "AI" while "g" + "ai"
// + "n" stays "gain" and is NOT a whole-word match.
//
// Returns array (per span index) of [{ start, end }] in local span coords.
export const computeSpanFlagRanges = (regex, spanTexts, hasEols = []) => {
    const perSpan = spanTexts.map(() => []);
    if (!regex) return perSpan;

    // Build combined text and each span's text range within it.
    const spanOffsets = [];
    let combined = '';
    spanTexts.forEach((text, i) => {
        const start = combined.length;
        combined += text;
        spanOffsets.push({ start, end: combined.length });
        if (hasEols[i]) combined += '\n';
    });

    for (const range of findFlagRanges(regex, combined)) {
        for (let i = 0; i < spanOffsets.length; i++) {
            const off = spanOffsets[i];
            if (off.end <= range.start) continue;
            if (off.start >= range.end) break;
            const localStart = Math.max(range.start - off.start, 0);
            const localEnd = Math.min(range.end - off.start, off.end - off.start);
            if (localEnd > localStart) {
                perSpan[i].push({ start: localStart, end: localEnd });
            }
        }
    }

    return perSpan;
};

// Build span innerHTML applying flag-word and search highlights.
// flagRanges/searchRanges: [{ start, end }] / [{ start, end, active }] in
// local span coordinates.
export const buildSpanInnerHtml = (rawText, flagRanges = [], searchRanges = []) => {
    const allRanges = [
        ...flagRanges.map(r => ({ start: r.start, end: r.end, type: 'flag' })),
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
