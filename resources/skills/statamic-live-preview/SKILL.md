---
name: statamic-live-preview
description: Annotates page-builder blocks for the Live Preview bridge with the {{ lp_target }} tag, so that clicking a block in the preview jumps to its set in the control panel. Use when creating or editing a page-builder block, a replicator set, a section, or a block partial under resources/views/page_builder/ — and on any mention of "Live Preview", "lp_target", "data-lp-set" or "click-to-edit".
---

# Live Preview markup for page-builder blocks

The `maxara/statamic-live-preview` addon highlights a block on hover in the Live Preview and
jumps to the matching set in the publish form on click. Every block needs **one** annotation in
its template for that. Without it the block simply is not clickable in the preview — and nobody
notices until an editor goes looking for it.

## The rule

When creating a new block partial, put `{{ lp_target }}` on the **outermost** element.

```antlers
<section class="bg-paper px-5 py-12"{{ lp_target }}>
    …
</section>
```

Three things that regularly go wrong:

- **No space before `{{`.** The tag brings its own leading space. Writing
  `class="…" {{ lp_target }}` leaves a double space in the markup.
- **Exactly one target per block.** Never additionally on child elements. The granularity is the
  block by design, not the field: jumping to the block is enough for an editor, and every further
  element would be permanent maintenance on every new block.
- **Never wrap it in `{{ if }}`.** The tag checks for itself whether the live preview is active
  and renders nothing outside it. The production footprint is zero.

## Partials that also run outside the page builder

If a block has no wrapper of its own but delegates to a shared partial, that partial must **not**
fall back to the context: outside the replicator loop `id` is the entry id, which the publish
form knows nothing about. The caller passes the set id through explicitly:

```antlers
{{# page_builder/_hero_image.antlers.html — has the set id #}}
{{ partial:partials/hero-media :image="image" :lp_set="id" }}

{{# partials/_hero-media.antlers.html — shared, unaware of its origin #}}
<section class="relative h-105"{{ lp_target :set="lp_set" }}>
```

An empty `set` parameter suppresses the output. A caller without `lp_set` — a location detail
page, say — correctly gets nothing.

## Replicator setting

Use `collapse: accordion` on the page-builder replicator, not `collapse: true`. Statamic then
expands only one set at a time, and `reveal.element()` triggers exactly that behaviour through
the `expanded` event: a click in the preview opens the target block and closes the others. Pure
configuration, no code.

## Colour

The highlight is a neutral blue (`#4f8ef7`) — deliberately not from the project palette, so it
reads as tooling. Override it with the `--lp-bridge-accent` custom property, both in the front
end and in the control panel.

## Verifying

A render test over the page-builder loop holds the order of the `data-lp-set` values against the
set ids. It catches exactly the case where a new block type forgets the annotation:

```php
public function test_every_block_in_the_page_builder_loop_is_annotated(): void
{
    $html = view('default', [
        'live_preview' => true,
        'page_builder' => [
            ['id' => 'HERO_ID', 'type' => 'hero_image'],
            ['id' => 'CTA_ID', 'type' => 'cta_band'],
        ],
    ])->withoutExtractions()->render();

    preg_match_all('/data-lp-set="([^"]*)"/', $html, $matches);

    $this->assertSame(['HERO_ID', 'CTA_ID'], $matches[1]);
}
```

Quick check on the command line — hold the number of annotated partials against the number of
sets in the fieldset:

```bash
grep -rl "lp_target" resources/views/page_builder/ | wc -l
```
