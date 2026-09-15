<?php

declare(strict_types=1);

namespace SchmidtMax\StatamicLivePreview\Listeners;

use Illuminate\Foundation\Vite as LaravelVite;
use Illuminate\Foundation\ViteException;
use Illuminate\Support\Facades\Log;
use Statamic\Events\ResponseCreated;
use Statamic\Statamic;

/**
 * Delivers the bridge into the previewed page.
 *
 * Statamic has no counterpart to $vite for the website — an addon's $vite renders
 * exclusively in the control-panel layout. ResponseCreated (dispatched in
 * DataResponse.php for every entry response, including the live-preview POSTs) is the
 * seam meant for this.
 *
 * That way a consuming project needs neither a marker in its layout, nor a gate in its
 * front-end bundle, nor a rule in its stylesheet.
 */
class InjectBridge
{
    private const PACKAGE = 'statamic-live-preview';

    private const ENTRY = 'resources/js/bridge.js';

    /**
     * No smooth scrolling in the preview.
     *
     * After swapping the iframe Statamic restores the scroll position via
     * scrollTo(x, y) — without a behavior option. A site's
     * `html { scroll-behavior: smooth }` turns that into an animation which restarts
     * every ~150 ms while typing and never arrives: the preview would sit at the top
     * of the page.
     *
     * Unlayered, so it wins against a rule in @layer base — without !important and
     * without the site having to provide a selector for it.
     */
    private const STYLE = '<style data-lp-bridge>html{scroll-behavior:auto}</style>';

    public function handle(ResponseCreated $event): void
    {
        if (! request()->isLivePreview()) {
            return;
        }

        $html = $event->response->getContent();

        if (! is_string($html)) {
            return;
        }

        if (! $url = $this->bridgeUrl()) {
            return;
        }

        $event->response->setContent($this->inject($html, $url));
    }

    /**
     * Pure function, so the rewriting stays testable without Vite or a faked request.
     */
    public function inject(string $html, string $scriptUrl): string
    {
        if (str_contains($html, '</head>')) {
            $html = str_replace('</head>', self::STYLE.'</head>', $html);
        }

        if (str_contains($html, '</body>')) {
            $tag = '<script type="module" src="'.e($scriptUrl).'"></script>';
            $html = str_replace('</body>', $tag.'</body>', $html);
        }

        return $html;
    }

    /**
     * The built bridge module, resolved from the addon's own Vite manifest.
     *
     * Paths are never hardcoded: registerVite() already computed hotFile and
     * buildDirectory when the addon booted, so we read them back out of the registry.
     * Cloned like in Statamic\Tags\Vite, so the shared instance is not mutated.
     */
    private function bridgeUrl(): ?string
    {
        $vite = Statamic::availableVites(request())[self::PACKAGE] ?? null;

        if (! $vite) {
            return null;
        }

        try {
            return (clone app(LaravelVite::class))
                ->useHotFile($vite['hotFile'])
                ->asset(self::ENTRY, $vite['buildDirectory']);
        } catch (ViteException $e) {
            // A missing manifest means the assets were never published. Failing loudly
            // here would break every preview, so say what to do and leave the page be.
            Log::warning(
                '[live-preview-bridge] Assets not published, the bridge stays inactive. '
                .'Run: php artisan vendor:publish --tag='.self::PACKAGE,
                ['exception' => $e->getMessage()],
            );

            return null;
        }
    }
}
