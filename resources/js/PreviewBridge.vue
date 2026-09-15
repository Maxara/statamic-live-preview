<script setup>
import { nextTick, onMounted, onUnmounted } from 'vue';
import { injectPublishContext } from '@statamic/cms/ui';
import { reveal } from '@statamic/cms/api';

// Rendered into the live preview header bar via statamic.live_preview.inputs, and only
// exists while the live preview is open. Renders nothing -- the component is purely the
// anchor point for reaching the publish context (LivePreview.vue sits inside the
// PublishContainer).
defineOptions({ inheritAttrs: false });
defineProps({
    value: { type: null, default: null },
    loading: { type: Boolean, default: false },
});

const INCOMING = 'lp-bridge.select';
const OUTGOING = 'lp-bridge.highlight';
const HIGHLIGHT_CLASS = 'lp-bridge-target';
const HIGHLIGHT_MS = 1600;

const STYLES = `
    .${HIGHLIGHT_CLASS} {
        outline: 2px solid var(--lp-bridge-accent, #4f8ef7);
        outline-offset: 3px;
        border-radius: 0.5rem;
    }
`;

const { values } = injectPublishContext();

let highlightTimer = null;

/**
 * Path of the set carrying this _id within the publish values, e.g. "page_builder.2".
 *
 * The loop index from the front end is unusable: augmentation filters out sets with
 * enabled: false and reindexes. So the id travels, and the index is resolved here.
 */
function findSetPath(value, setId, prefix = '') {
    if (Array.isArray(value)) {
        for (let index = 0; index < value.length; index++) {
            const row = value[index];
            if (!row || typeof row !== 'object') continue;

            const path = prefix ? `${prefix}.${index}` : String(index);
            if (row._id === setId) return path;

            const nested = findSetPath(row, setId, path);
            if (nested) return nested;
        }

        return null;
    }

    if (value && typeof value === 'object') {
        for (const [key, child] of Object.entries(value)) {
            if (key === '_id') continue;

            const found = findSetPath(child, setId, prefix ? `${prefix}.${key}` : key);
            if (found) return found;
        }
    }

    return null;
}

/** Publish/Field.vue assigns the DOM id as field_<path with _ instead of .>. */
function fieldId(path) {
    return `field_${path.replaceAll('.', '_')}`;
}

/**
 * Root element of the set.
 *
 * A collapsed set does not render its fields, so there is no field element to hold on
 * to -- we go by position instead, because the DOM order of the sets matches the array
 * order.
 *
 * Both routes depend on CP internals that Statamic does not guarantee, hence one after
 * the other with a fallback:
 *   1. the sortable class Replicator.vue assigns per set (<fieldId>-sortable-item)
 *   2. [data-replicator-set] within the replicator field, nested ones filtered out
 */
function findSetElement(setPath) {
    const parts = setPath.split('.');
    const index = Number(parts.pop());
    const replicatorId = fieldId(parts.join('.'));

    const byClass = document.getElementsByClassName(`${replicatorId}-sortable-item`);
    if (byClass[index]) return byClass[index];

    const wrapper = document.getElementById(replicatorId);
    if (!wrapper) return null;

    return (
        [...wrapper.querySelectorAll('[data-replicator-set]')].filter(
            (el) => el.parentElement?.closest('[data-replicator-set]') === null,
        )[index] ?? null
    );
}

/** The set's box -- depending on the route, setEl is that box or its container. */
function setBox(setEl) {
    return setEl.matches('[data-replicator-set]')
        ? setEl
        : (setEl.querySelector('[data-replicator-set]') ?? setEl);
}

/**
 * Visible feedback. Without it a jump goes unnoticed when the set was already in view
 * or when all of its fields happen to be empty.
 */
function highlight(box) {
    clearTimeout(highlightTimer);
    document.querySelector(`.${HIGHLIGHT_CLASS}`)?.classList.remove(HIGHLIGHT_CLASS);

    box.classList.add(HIGHLIGHT_CLASS);
    highlightTimer = setTimeout(() => box.classList.remove(HIGHLIGHT_CLASS), HIGHLIGHT_MS);
}

/**
 * Correct the position afterwards.
 *
 * reveal.element() centres the element it is given (scrollIntoView block: 'center').
 * For a tall set that leaves the beginning off screen or underneath the header bar. So
 * we scroll the set's head to the top and then measure via elementFromPoint whether
 * anything covers it -- the overlap is applied as scroll-margin-top and we scroll once
 * more. That needs no assumption about the height of the CP bars and adapts when they
 * change.
 */
function scrollIntoClearView(box) {
    const anchor = box.querySelector(':scope > header') ?? box;

    anchor.scrollIntoView({ block: 'start', behavior: 'instant' });

    const rect = anchor.getBoundingClientRect();
    const covering = document.elementFromPoint(
        Math.max(1, rect.left + rect.width / 2),
        rect.top + 2,
    );

    // An ancestor "covers" nothing -- the overlap would be its entire box.
    if (!covering || covering === anchor || anchor.contains(covering) || covering.contains(anchor)) {
        return;
    }

    const overlap = covering.getBoundingClientRect().bottom - rect.top;
    if (overlap <= 0) return;

    // scroll-margin only matters at the moment of scrolling; it can go afterwards.
    anchor.style.scrollMarginTop = `${Math.ceil(overlap) + 8}px`;
    anchor.scrollIntoView({ block: 'start', behavior: 'instant' });
    anchor.style.scrollMarginTop = '';
}

async function onMessage(event) {
    if (event.origin !== window.location.origin) return;
    if (event.data?.name !== INCOMING) return;

    const { set } = event.data;

    const setPath = findSetPath(values.value, set);
    if (!setPath) {
        console.warn(`[live-preview-bridge] Set "${set}" not found in the publish values.`);

        return;
    }

    const setEl = findSetElement(setPath);
    if (!setEl) {
        console.warn(`[live-preview-bridge] No DOM element for set "${setPath}" found.`);

        return;
    }

    // reveal.element() fires the reveal callbacks of the replicator set and the publish
    // tab on its way up -- so it expands the set and switches the tab.
    reveal.element(setEl);

    const box = setBox(setEl);
    highlight(box);

    // reveal.element() scrolls in a nextTick of its own; correct afterwards or it
    // overwrites our position. Wait for the frame so a tab switch or an expansion has
    // finished laying out before we measure.
    await nextTick();
    requestAnimationFrame(() => scrollIntoClearView(box));
}

/** The other direction: focus in the CP marks the block in the preview. */
function onFocusIn(event) {
    const fieldEl = event.target.closest?.('[id^="field_"]');
    if (!fieldEl) return;

    const iframe = document.getElementById('live-preview-iframe');
    if (!iframe) return;

    // field_page_builder_2_headline -> Set page_builder.2
    const match = fieldEl.id.match(/^field_(.*?)_(\d+)(?:_|$)/);
    if (!match) return;

    const set = values.value?.[match[1]]?.[Number(match[2])]?._id;
    if (!set) return;

    iframe.contentWindow.postMessage({ name: OUTGOING, set }, window.location.origin);
}

onMounted(() => {
    const style = document.createElement('style');
    style.dataset.lpBridge = '';
    style.textContent = STYLES;
    document.head.append(style);

    window.addEventListener('message', onMessage);
    document.addEventListener('focusin', onFocusIn);
});

onUnmounted(() => {
    clearTimeout(highlightTimer);
    document.querySelector('style[data-lp-bridge]')?.remove();
    window.removeEventListener('message', onMessage);
    document.removeEventListener('focusin', onFocusIn);
});
</script>

<template>
    <span class="hidden" aria-hidden="true" />
</template>
