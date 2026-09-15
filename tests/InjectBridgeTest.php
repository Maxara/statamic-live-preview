<?php

declare(strict_types=1);

namespace SchmidtMax\StatamicLivePreview\Tests;

use SchmidtMax\StatamicLivePreview\Listeners\InjectBridge;

/**
 * The listener delivers the bridge script and the scroll-behavior reset into the
 * previewed page. inject() is tested directly — handle() only adds the
 * request()->isLivePreview() guard and the asset lookup on top.
 */
class InjectBridgeTest extends TestCase
{
    private const PAGE = '<!doctype html><html><head><title>T</title></head><body><p>Hi</p></body></html>';

    private const URL = '/vendor/statamic-live-preview/build/assets/bridge-abc123.js';

    private function inject(string $html, string $url = self::URL): string
    {
        return app(InjectBridge::class)->inject($html, $url);
    }

    public function test_it_resets_smooth_scrolling_before_the_head_closes(): void
    {
        $html = $this->inject(self::PAGE);

        $this->assertStringContainsString('scroll-behavior:auto', $html);
        $this->assertLessThan(
            strpos($html, '</head>'),
            strpos($html, 'scroll-behavior:auto'),
            'The style has to sit inside the <head>.'
        );
    }

    public function test_it_injects_the_bridge_module_before_the_body_closes(): void
    {
        $html = $this->inject(self::PAGE);

        $tag = '<script type="module" src="'.self::URL.'"></script>';

        $this->assertStringContainsString($tag, $html);
        $this->assertLessThan(
            strpos($html, '</body>'),
            strpos($html, $tag),
            'The script has to sit at the end of the <body>.'
        );
    }

    public function test_it_escapes_the_script_url(): void
    {
        $html = $this->inject(self::PAGE, '/a"onload="alert(1)');

        $this->assertStringNotContainsString('onload="alert(1)', $html);
        $this->assertStringContainsString('&quot;', $html);
    }

    public function test_it_leaves_the_original_markup_intact(): void
    {
        $html = $this->inject(self::PAGE);

        $this->assertStringContainsString('<p>Hi</p>', $html);
        $this->assertStringContainsString('<title>T</title>', $html);
    }

    public function test_it_leaves_a_fragment_without_head_and_body_alone(): void
    {
        $fragment = '<div>just a snippet</div>';

        $this->assertSame($fragment, $this->inject($fragment));
    }

    /**
     * A page may legitimately contain the literal closing tags again — inside a script
     * string, for instance. Replacing every occurrence would scatter copies of the
     * script through the document, so the injection anchors on the document's own tags:
     * the first </head> and the last </body>.
     */
    public function test_it_injects_once_even_when_the_markup_repeats_the_closing_tags(): void
    {
        $page = '<!doctype html><html><head><title>T</title></head><body>'
            .'<script>var a = "</body>";</script><p>Hi</p></body></html>';

        $html = $this->inject($page);

        $this->assertSame(1, substr_count($html, '<script type="module"'));
        $this->assertSame(1, substr_count($html, 'scroll-behavior:auto'));

        // The decoy inside the script must be untouched, and ours must sit after it.
        $this->assertGreaterThan(
            strpos($html, 'var a ='),
            strpos($html, '<script type="module"'),
        );
    }
}
