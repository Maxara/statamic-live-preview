const i="data-lp-set",s="lp-bridge.select",u="lp-bridge.highlight";const p=`
    [${i}] { cursor: pointer; }

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
`;let n=null,o=null,r=null;function d(e){return e instanceof Element?e.closest(`[${i}]`):null}function l(e,t){n?.isConnected||(n=document.createElement("div"),n.dataset.lpOverlay="",document.body.append(n));const a=e.getBoundingClientRect();n.style.top=`${a.top+window.scrollY-8}px`,n.style.left=`${a.left+window.scrollX+2}px`,n.style.width=`${Math.max(0,a.width-4)}px`,n.style.height=`${Math.max(0,a.height+16)}px`,n.dataset.variant=t,n.hidden=!1}function c(){n&&(n.hidden=!0)}function f(e){const t=d(e.target);if(t!==o){if(o=t,!t){c();return}r||l(t,"hover")}}function g(e){e.relatedTarget&&d(e.relatedTarget)===o||(o=null,r||c())}function h(e){const t=d(e.target);t&&(e.altKey||e.metaKey||(e.preventDefault(),window.parent.postMessage({name:s,set:t.getAttribute(i)},window.location.origin)))}function v(e){if(e.origin!==window.location.origin||e.data?.name!==u)return;const t=document.querySelector(`[${i}="${CSS.escape(e.data.set)}"]`);t&&(t.scrollIntoView({block:"center",behavior:"instant"}),clearTimeout(r),l(t,"flash"),r=setTimeout(()=>{r=null,o?l(o,"hover"):c()},1600))}function w(){if(window.__lpBridge||window.self===window.top)return;window.__lpBridge=!0;const e=document.createElement("style");e.dataset.lpBridge="",e.textContent=p,document.head.append(e),document.addEventListener("pointerover",f),document.addEventListener("pointerout",g),document.addEventListener("click",h),window.addEventListener("message",v)}w();
