import { test } from 'node:test';
import assert from 'node:assert/strict';
import {
    compileFlagRegex,
    findFlagRanges,
    computeSpanFlagRanges,
    buildSpanInnerHtml,
} from '../../resources/js/lib/flagHighlight.js';

const wholeAi = compileFlagRegex('/(\\bai\\b)/ui');   // whole_word ai
const rootDivers = compileFlagRegex('/(\\bdivers)/ui'); // word_root divers

test('backend whole-word pattern matches standalone AI in JS', () => {
    assert.equal(findFlagRanges(wholeAi, 'AI is used (ai) here').length, 2);
});

test('backend whole-word pattern does not match aim, gain, training', () => {
    assert.equal(findFlagRanges(wholeAi, 'aim gain training air said').length, 0);
});

test('whole-word ai: split g|ai|n forms "gain" — no match', () => {
    const ranges = computeSpanFlagRanges(wholeAi, ['g', 'ai', 'n'], []);
    assert.deepEqual(ranges, [[], [], []]);
});

test('whole-word ai: split a|im forms "aim" — no match', () => {
    const ranges = computeSpanFlagRanges(wholeAi, ['a', 'im'], []);
    assert.deepEqual(ranges, [[], []]);
});

test('whole-word ai: standalone span matches', () => {
    const ranges = computeSpanFlagRanges(wholeAi, ['the ', 'AI', ' model'], []);
    assert.deepEqual(ranges[1], [{ start: 0, end: 2 }]);
});

test('whole-word ai: split A|I joins seamlessly and matches across spans', () => {
    const ranges = computeSpanFlagRanges(wholeAi, ['the ', 'A', 'I', ' model'], []);
    assert.deepEqual(ranges[1], [{ start: 0, end: 1 }]);
    assert.deepEqual(ranges[2], [{ start: 0, end: 1 }]);
});

test('hasEOL separator creates a boundary across line breaks', () => {
    // "...end" + hasEOL + "AI" — newline keeps 'ai' out of 'end' and bounds AI.
    const ranges = computeSpanFlagRanges(wholeAi, ['end of line', 'ai starts'], [true, false]);
    assert.deepEqual(ranges[1], [{ start: 0, end: 2 }]);
});

test('word_root pattern still matches inside derivatives', () => {
    const ranges = computeSpanFlagRanges(rootDivers, ['diverse', ' diversity'], []);
    assert.ok(ranges[0].length === 1 && ranges[1].length === 1);
});

test('buildSpanInnerHtml escapes text and wraps ranges in mark tags', () => {
    const html = buildSpanInnerHtml('<b>ai</b> rest', [{ start: 3, end: 5 }], []);
    assert.equal(html, '&lt;b&gt;<mark class="doc-flag-highlight">ai</mark>&lt;/b&gt; rest');
});
