/**
 * Live preview bridge (the previewed page's half).
 *
 * Highlights annotated blocks on hover and reports a click to the control panel via
 * postMessage, which then jumps to the matching set. The other direction: the CP can
 * ask for a block to be marked.
 *
 * It also keeps the iframe on the previewed page: a plain click on a link that would
 * leave it, or a form submit, is cancelled. Otherwise the preview would show another
 * page while the publish form still edits the original entry.
 *
 * Injected into the previewed page by the addon's InjectBridge listener -- so it only
 * ever runs there, with no marker in the layout and no gate in the site's bundle. The
 * data-lp-set attributes come from the {{ lp_target }} tag.
 */

const SET_ATTR = 'data-lp-set';
const OUTGOING = 'lp-bridge.select';
const INCOMING = 'lp-bridge.highlight';
const FLASH_MS = 1600;

/*
 * The highlight is a separate overlay element, not an outline on the block itself:
 *   - The distance is freely chosen. Blocks commonly butt against each other without a
 *     gap, so an outline on the element clings to the neighbour's content.
 *   - The frame has to sit outside for that. On the element, outline-offset would
 *     overlap the adjacent block; a free-floating overlay merely covers.
 *   - Rounded corners and a light tint are possible without touching border-radius or
 *     background on the content -- the preview should look like the page.
 *
 * Widened vertically, inset slightly horizontally: full-bleed blocks run edge to edge,
 * and side lines placed outside them would be clipped at the viewport edge.
 */
const PAD_Y = 8;
const INSET_X = 2;

const STYLES = `
    [${SET_ATTR}] { cursor: pointer; }

    [data-lp-overlay] {
        /* Deliberately not a project palette colour: the overlay should read as
           tooling, not as part of the page. Override via --lp-bridge-accent. */
        --lp-accent: var(--lp-bridge-accent, #4f8ef7);
        position: absolute;
        z-index: 45;
        pointer-events: none;
        border-radius: 12px;
    }

    [data-lp-overlay][hidden] { display: none; }

    [data-lp-overlay][data-variant="hover"] {
        outline: 2px dashed color-mix(in srgb, var(--lp-accent) 70%, transparent);
        background: color-mix(in srgb, var(--lp-accent) 6%, transparent);
    }

    [data-lp-overlay][data-variant="flash"] {
        outline: 3px solid var(--lp-accent);
        background: color-mix(in srgb, var(--lp-accent) 10%, transparent);
    }
`;

let overlay = null;
let hovered = null;
let flashTimer = null;

function targetFor(node) {
    return node instanceof Element ? node.closest(`[${SET_ATTR}]`) : null;
}

function show(el, variant) {
    if (!overlay?.isConnected) {
        overlay = document.createElement('div');
        overlay.dataset.lpOverlay = '';
        document.body.append(overlay);
    }

    // Document coordinates, so the overlay stays on the block while scrolling.
    const rect = el.getBoundingClientRect();
    overlay.style.top = `${rect.top + window.scrollY - PAD_Y}px`;
    overlay.style.left = `${rect.left + window.scrollX + INSET_X}px`;
    overlay.style.width = `${Math.max(0, rect.width - INSET_X * 2)}px`;
    overlay.style.height = `${Math.max(0, rect.height + PAD_Y * 2)}px`;
    overlay.dataset.variant = variant;
    overlay.hidden = false;
}

function hide() {
    if (overlay) overlay.hidden = true;
}

function onPointerOver(event) {
    const el = targetFor(event.target);
    if (el === hovered) return;

    hovered = el;

    if (!el) {
        hide();

        return;
    }

    // A running flash from the CP must not be overwritten by hover.
    if (!flashTimer) show(el, 'hover');
}

function onPointerOut(event) {
    if (event.relatedTarget && targetFor(event.relatedTarget) === hovered) return;

    hovered = null;
    if (!flashTimer) hide();
}

function hasModifier(event) {
    return event.altKey || event.metaKey || event.ctrlKey || event.shiftKey;
}

// Anchors on the same page (skip links, table of contents) and links opening another
// tab or window leave the previewed document in place, so they stay usable.
function leavesPage(node) {
    const link = node instanceof Element ? node.closest('a[href]') : null;
    if (!link) return false;

    const target = link.getAttribute('target');
    if (target && target !== '_self') return false;

    const url = new URL(link.href, window.location.href);
    const isSameDocument =
        url.origin === window.location.origin &&
        url.pathname === window.location.pathname &&
        url.search === window.location.search;

    return !(isSameDocument && url.hash);
}

function onClick(event) {
    // Modifier clicks open a new tab or window (or download) and leave the iframe in
    // place, so links stay testable.
    if (hasModifier(event)) return;

    const setEl = targetFor(event.target);
    if (!setEl) {
        if (leavesPage(event.target)) event.preventDefault();

        return;
    }

    event.preventDefault();

    window.parent.postMessage(
        { name: OUTGOING, set: setEl.getAttribute(SET_ATTR) },
        window.location.origin,
    );
}

function onSubmit(event) {
    event.preventDefault();
}

function onMessage(event) {
    if (event.origin !== window.location.origin) return;
    if (event.data?.name !== INCOMING) return;

    const setEl = document.querySelector(`[${SET_ATTR}="${CSS.escape(event.data.set)}"]`);
    if (!setEl) return;

    setEl.scrollIntoView({ block: 'center', behavior: 'instant' });

    clearTimeout(flashTimer);
    show(setEl, 'flash');
    flashTimer = setTimeout(() => {
        flashTimer = null;
        hovered ? show(hovered, 'hover') : hide();
    }, FLASH_MS);
}

function init() {
    // The iframe is replaced wholesale on every change, so the module normally runs
    // exactly once per document. The guard costs nothing and covers the case where the
    // page evaluates it a second time anyway.
    if (window.__lpBridge) return;

    // The popout window is top level and talks over BroadcastChannel -- the bridge does
    // not apply there.
    if (window.self === window.top) return;

    window.__lpBridge = true;

    const style = document.createElement('style');
    style.dataset.lpBridge = '';
    style.textContent = STYLES;
    document.head.append(style);

    // Delegated, because the iframe is reloaded in full on every change.
    document.addEventListener('pointerover', onPointerOver);
    document.addEventListener('pointerout', onPointerOut);
    document.addEventListener('click', onClick);
    document.addEventListener('submit', onSubmit);
    window.addEventListener('message', onMessage);
}

init();
