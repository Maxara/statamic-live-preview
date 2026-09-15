<?php

declare(strict_types=1);

namespace Seitwerk\StatamicLivePreview\Tests;

use Seitwerk\StatamicLivePreview\Tags\Target;

/**
 * The {{ lp_target }} tag may only ever render something during the live preview —
 * outside it the footprint is zero.
 */
class TargetTagTest extends TestCase
{
    /**
     * The tag class directly rather than through Antlers::parse(): there the tag would
     * not receive the given data as $context. Rendering through real templates is the
     * consuming project's business, not the package's.
     */
    private function render(array $context, array $params = []): string
    {
        $tag = new Target;
        $tag->setContext($context);
        $tag->setParameters($params);

        return $tag->index();
    }

    public function test_it_emits_nothing_outside_live_preview(): void
    {
        $this->assertSame('', $this->render(['id' => 'abc123']));
    }

    public function test_it_reads_the_set_id_from_the_context(): void
    {
        $html = $this->render(['live_preview' => true, 'id' => 'abc123']);

        $this->assertSame(' data-lp-set="abc123"', $html);
    }

    public function test_the_set_parameter_overrides_the_context(): void
    {
        $html = $this->render(
            ['live_preview' => true, 'id' => 'abc123'],
            ['set' => 'override'],
        );

        $this->assertSame(' data-lp-set="override"', $html);
    }

    /**
     * A partial that is also rendered outside the page builder passes its set parameter
     * through and gets nothing there. Without this branch the tag would find the entry
     * id in the context and annotate an id the publish form does not know.
     */
    public function test_an_empty_set_parameter_suppresses_the_context_fallback(): void
    {
        $html = $this->render(
            ['live_preview' => true, 'id' => 'entry-id'],
            ['set' => null],
        );

        $this->assertSame('', $html);
    }

    public function test_it_emits_nothing_without_an_id(): void
    {
        $this->assertSame('', $this->render(['live_preview' => true]));
    }

    public function test_it_escapes_the_id(): void
    {
        $html = $this->render(['live_preview' => true], ['set' => 'a"b']);

        $this->assertStringNotContainsString('a"b', $html);
        $this->assertStringContainsString('&quot;', $html);
    }

    /**
     * The leading space is the tag's own, because it sits in attribute position —
     * `<section class="x"{{ lp_target }}>` must not collapse into a broken attribute.
     */
    public function test_it_brings_its_own_leading_space(): void
    {
        $html = $this->render(['live_preview' => true, 'id' => 'abc123']);

        $this->assertStringStartsWith(' ', $html);
    }
}
