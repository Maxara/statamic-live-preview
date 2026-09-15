/**
 * Live preview bridge (the previewed page's half).
 *
 * Highlights annotated blocks on hover and reports a click to the control panel via
 * postMessage, which then jumps to the matching set. The other direction: the CP can
 * ask for a block to be marked.
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

function onClick(event) {
    const setEl = targetFor(event.target);
    if (!setEl) return;

    // Alt/Cmd lets normal navigation through, so links stay testable.
    if (event.altKey || event.metaKey) return;
    event.preventDefault();

    window.parent.postMessage(
        { name: OUTGOING, set: setEl.getAttribute(SET_ATTR) },
        window.location.origin,
    );
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
    window.addEventListener('message', onMessage);
}

init();
