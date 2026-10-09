const l="data-lp-set",u="lp-bridge.select",p="lp-bridge.highlight";const f=`
    [${l}] { cursor: pointer; }

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
`;let n=null,r=null,a=null;function c(t){return t instanceof Element?t.closest(`[${l}]`):null}function s(t,e){n?.isConnected||(n=document.createElement("div"),n.dataset.lpOverlay="",document.body.append(n));const o=t.getBoundingClientRect();n.style.top=`${o.top+window.scrollY-8}px`,n.style.left=`${o.left+window.scrollX+2}px`,n.style.width=`${Math.max(0,o.width-4)}px`,n.style.height=`${Math.max(0,o.height+16)}px`,n.dataset.variant=e,n.hidden=!1}function d(){n&&(n.hidden=!0)}function h(t){const e=c(t.target);if(e!==r){if(r=e,!e){d();return}a||s(e,"hover")}}function g(t){t.relatedTarget&&c(t.relatedTarget)===r||(r=null,a||d())}function m(t){return t.altKey||t.metaKey||t.ctrlKey||t.shiftKey}function w(t){const e=t instanceof Element?t.closest("a[href]"):null;if(!e)return!1;const o=e.getAttribute("target");if(o&&o!=="_self")return!1;const i=new URL(e.href,window.location.href);return!(i.origin===window.location.origin&&i.pathname===window.location.pathname&&i.search===window.location.search&&i.hash)}function v(t){if(m(t))return;const e=c(t.target);if(!e){w(t.target)&&t.preventDefault();return}t.preventDefault(),window.parent.postMessage({name:u,set:e.getAttribute(l)},window.location.origin)}function y(t){t.preventDefault()}function b(t){if(t.origin!==window.location.origin||t.data?.name!==p)return;const e=document.querySelector(`[${l}="${CSS.escape(t.data.set)}"]`);e&&(e.scrollIntoView({block:"center",behavior:"instant"}),clearTimeout(a),s(e,"flash"),a=setTimeout(()=>{a=null,r?s(r,"hover"):d()},1600))}function E(){if(window.__lpBridge||window.self===window.top)return;window.__lpBridge=!0;const t=document.createElement("style");t.dataset.lpBridge="",t.textContent=f,document.head.append(t),document.addEventListener("pointerover",h),document.addEventListener("pointerout",g),document.addEventListener("click",v),document.addEventListener("submit",y),window.addEventListener("message",b)}E();
