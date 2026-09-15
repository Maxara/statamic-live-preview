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
    /**
     * Must match the second half of this package's composer name — that is what
     * Addon::packageName() derives the Vite registry key from. Pinned by
     * AddonRegistrationTest against composer.json.
     */
    public const PACKAGE = 'statamic-live-preview';

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

    /**
     * The preview re-renders on every keystroke. Without this, a misconfigured install
     * would write a warning several times per second for as long as an editor keeps
     * typing.
     */
    private static bool $warned = false;

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
     *
     * Anchored on the first </head> and the last </body> rather than replacing every
     * occurrence: a page that renders escaped-but-decoded markup, or carries the
     * literal inside a script string, would otherwise collect stray copies mid-document.
     */
    public function inject(string $html, string $scriptUrl): string
    {
        $html = $this->insertBefore($html, '</head>', self::STYLE, first: true);

        $tag = '<script type="module" src="'.e($scriptUrl).'"></script>';

        return $this->insertBefore($html, '</body>', $tag, first: false);
    }

    private function insertBefore(string $html, string $needle, string $insert, bool $first): string
    {
        $position = $first ? strpos($html, $needle) : strrpos($html, $needle);

        if ($position === false) {
            return $html;
        }

        return substr_replace($html, $insert, $position, 0);
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
            $this->warnOnce(sprintf(
                'No Vite registration named "%s". The addon did not boot as expected — '
                .'check that its service provider is discovered.',
                self::PACKAGE,
            ));

            return null;
        }

        try {
            return (clone app(LaravelVite::class))
                ->useHotFile($vite['hotFile'])
                ->asset(self::ENTRY, $vite['buildDirectory']);
        } catch (ViteException $e) {
            // A missing manifest means the assets were never published. Failing loudly
            // here would break every preview, so say what to do and leave the page be.
            $this->warnOnce(
                'Assets not published, the bridge stays inactive. '
                .'Run: php artisan vendor:publish --tag='.self::PACKAGE,
                $e,
            );

            return null;
        }
    }

    private function warnOnce(string $message, ?ViteException $exception = null): void
    {
        if (self::$warned) {
            return;
        }

        self::$warned = true;

        Log::warning('[live-preview-bridge] '.$message, array_filter([
            'exception' => $exception?->getMessage(),
        ]));
    }
}
