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

// `name` is the publish container's name. Since Statamic 6.3x it is part of every field's
// DOM id (field_<container>_<path>), and it defaults to a random uniqid -- so the id can
// only be rebuilt from the context, not guessed.
const { values, name: containerName } = injectPublishContext();

let highlightTimer = null;

/**
 * A Bard set node: { type: 'set', attrs: { id, enabled, values } }.
 *
 * Replicator rows always carry _id, so a replicator set that happens to be called "set"
 * and has a field "attrs" is not mistaken for one.
 */
function isBardSet(row) {
    return (
        row?.type === 'set' &&
        !('_id' in row) &&
        typeof row.attrs === 'object' &&
        row.attrs !== null &&
        typeof row.attrs.id === 'string'
    );
}

/** Replicator rows carry their id as _id, Bard set nodes as attrs.id. */
function setIdOf(row) {
    if (!row || typeof row !== 'object') return undefined;

    return row._id ?? (isBardSet(row) ? row.attrs.id : undefined);
}

/**
 * Path of the set carrying this id within the publish values, e.g. "page_builder.2" for
 * a replicator or "content.3" for a Bard set (the index of the node in the document).
 *
 * The loop index from the front end is unusable: augmentation filters out sets with
 * enabled: false and, for Bard, interleaves text chunks. So the id travels, and the
 * index is resolved here.
 */
function findSetPath(value, setId, prefix = '') {
    if (Array.isArray(value)) {
        for (let index = 0; index < value.length; index++) {
            const row = value[index];
            if (!row || typeof row !== 'object') continue;

            const path = prefix ? `${prefix}.${index}` : String(index);
            if (setIdOf(row) === setId) return path;

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

function valueAt(path) {
    return path.split('.').reduce((value, key) => value?.[key], values.value);
}

/**
 * Prefixes a field's DOM id can start with, most specific first.
 *
 * Publish/Field.vue builds the id as field_<container>_<path with _ instead of .>; up to
 * Statamic 6.2x it was field_<path>. Both are tried so the bridge works on either side
 * of that change.
 */
function fieldIdPrefixes() {
    const name = containerName?.value;

    return name ? [`field_${name}_`, 'field_'] : ['field_'];
}

function elementForField(path) {
    const suffix = path.replaceAll('.', '_');

    for (const prefix of fieldIdPrefixes()) {
        const el = document.getElementById(prefix + suffix);
        if (el) return { el, id: prefix + suffix };
    }

    return null;
}

/** field_<container>_content_3_attrs_values_text -> content_3_attrs_values_text */
function pathFromFieldId(id) {
    const prefix = fieldIdPrefixes().find((candidate) => id.startsWith(candidate));

    return prefix ? id.slice(prefix.length) : null;
}

/**
 * The set in the DOM: `target` goes to reveal.element(), `box` gets the highlight.
 *
 * Both depend on CP internals that Statamic does not guarantee, hence several routes
 * one after the other, each failing over to the next.
 */
function findSetElement(setPath) {
    const parts = setPath.split('.');
    const index = Number(parts.pop());
    const parentPath = parts.join('.');

    return isBardSet(valueAt(setPath))
        ? findBardSetElement(setPath, parentPath, index)
        : findReplicatorSetElement(parentPath, index);
}

/**
 * A collapsed replicator set does not render its fields, so there is no field element to
 * hold on to -- we go by position instead, because the DOM order of the sets matches the
 * array order.
 *   1. the sortable class Replicator.vue assigns per set (<fieldId>-sortable-item)
 *   2. [data-replicator-set] within the replicator field, nested ones filtered out
 */
function findReplicatorSetElement(replicatorPath, index) {
    const replicator = elementForField(replicatorPath);
    const ids = replicator
        ? [replicator.id]
        : fieldIdPrefixes().map((prefix) => prefix + replicatorPath.replaceAll('.', '_'));

    for (const id of ids) {
        const byClass = document.getElementsByClassName(`${id}-sortable-item`)[index];
        if (byClass) return { target: byClass, box: replicatorBox(byClass) };
    }

    if (!replicator) return null;

    const set = [...replicator.el.querySelectorAll('[data-replicator-set]')].filter(
        (el) => el.parentElement?.closest('[data-replicator-set]') === null,
    )[index];

    return set ? { target: set, box: set } : null;
}

/** The sortable item wraps the set's box. */
function replicatorBox(el) {
    return el.matches('[data-replicator-set]') ? el : (el.querySelector('[data-replicator-set]') ?? el);
}

/**
 * A Bard set is a ProseMirror node view. Its fields stay in the DOM while collapsed
 * (v-show), and Bard/Set.vue registers its container with reveal.mount() -- so reveal
 * expands it once it is handed the container, never the wrapper around it.
 *   1. any field of the set (id prefix <set path>_attrs_values_), up to its node view
 *   2. the set's position among the editor's top-level nodes, which matches the index
 */
function findBardSetElement(setPath, bardPath, index) {
    const fieldPrefix = `${setPath.replaceAll('.', '_')}_attrs_values_`;

    for (const prefix of fieldIdPrefixes()) {
        const field = document.querySelector(`[id^="${prefix}${fieldPrefix}"]`);
        const container = bardContainer(field?.closest('[data-node-view-wrapper]'));
        if (container) return { target: container, box: container };
    }

    const editor = elementForField(bardPath)?.el.querySelector('.ProseMirror');
    if (!editor) return null;

    // ProseMirror renders one DOM child per top-level node; gap cursor and widgets are
    // decorations that sit in between and must not shift the count.
    const node = [...editor.children].filter(
        (el) => !el.matches('.ProseMirror-gapcursor, .ProseMirror-widget'),
    )[index];

    const container = bardContainer(
        node?.matches('[data-node-view-wrapper]') ? node : node?.querySelector('[data-node-view-wrapper]'),
    );

    return container ? { target: container, box: container } : null;
}

/** The node view wrapper holds the set's container as its (non-editable) child. */
function bardContainer(wrapper) {
    if (!wrapper) return null;

    return wrapper.querySelector(':scope > [contenteditable="false"]') ?? wrapper.firstElementChild ?? wrapper;
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

    const found = findSetElement(setPath);
    if (!found) {
        console.warn(`[live-preview-bridge] No DOM element for set "${setPath}" found.`);

        return;
    }

    // reveal.element() fires the reveal callbacks of the set and the publish tab on its
    // way up -- so it expands the set and switches the tab.
    reveal.element(found.target);

    const { box } = found;
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

    // Replicator: field_<container>_page_builder_2_headline     -> page_builder.2
    // Bard:       field_<container>_content_3_attrs_values_text -> content.3
    const match = pathFromFieldId(fieldEl.id)?.match(/^(.*?)_(\d+)(?:_|$)/);
    if (!match) return;

    const set = setIdOf(values.value?.[match[1]]?.[Number(match[2])]);
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
