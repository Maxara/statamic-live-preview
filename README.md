# Live Preview Bridge

Click-to-edit for the Statamic Live Preview: hovering a page-builder block highlights it in the
preview, and clicking it jumps to the matching set in the control panel — expanded, on the right
tab, briefly outlined.

Statamic 6 ships no visual editing, but it ships every primitive needed for this. The addon wires
them together: `{{ live_preview }}` as the guard, the stable replicator set `id`, a same-origin
iframe, and `reveal.element()`.

## Installation

```bash
composer require maxara/statamic-live-preview
```

Assets are published to `public/vendor/statamic-live-preview/` automatically by
`statamic:install`, which runs on every `composer install` via `post-autoload-dump`. To do it by
hand:

```bash
php artisan vendor:publish --tag=statamic-live-preview
```

Add the publish target to your `.gitignore` — it is a build artifact:

```
/public/vendor/statamic-live-preview
```

## Usage

Put a single `{{ lp_target }}` on the outermost element of each block partial. Note there is no
space before `{{` — the tag brings its own:

```antlers
<section class="bg-paper px-5 py-12"{{ lp_target }}>
    …
</section>
```

The tag reads the set `id` from the replicator loop's context and renders **nothing** outside the
live preview, so the production footprint is zero.

### Shared partials

A partial that is also rendered outside the page builder must not fall back to the context —
there, `id` is the entry id, which the publish form knows nothing about. Let the caller pass the
set id through explicitly; an empty value suppresses the output:

```antlers
{{ partial:partials/hero-media :lp_set="id" }}   {{# inside the page builder #}}
<section{{ lp_target :set="lp_set" }}>           {{# inside the shared partial #}}
```

### Recommended replicator setting

Use `collapse: accordion` rather than `collapse: true`. Statamic then expands only one set at a
time, so a click in the preview opens the target block and closes the others.

### Colour

The highlight is a neutral blue (`#4f8ef7`) on purpose — it should read as tooling, not as part
of the page. Override it with the `--lp-bridge-accent` custom property, in your site stylesheet
for the preview and in a control-panel stylesheet for the editor side.

## Claude skill

The package ships a [Claude Code](https://claude.com/claude-code) skill that applies the correct
markup when new page-builder blocks are created. It is deliberately **not** installed
automatically — an addon has no business writing into someone else's tooling config unasked:

```bash
php artisan vendor:publish --tag=statamic-live-preview-skill
```

It lands in `.claude/skills/statamic-live-preview/`. It is a copy, so re-publish with `--force`
after an update that changes it.

## How it works

| File | Role |
| --- | --- |
| `src/Tags/Target.php` | `{{ lp_target }}` — writes `data-lp-set` |
| `src/Listeners/InjectBridge.php` | appends `bridge.js` to the preview response |
| `resources/js/bridge.js` | overlay and click handling inside the iframe, `postMessage` to the CP |
| `resources/js/PreviewBridge.vue` | control-panel side: id → array path, `reveal.element()` |

An addon's `$vite` renders **only** in the control-panel layout, so the front-end module has no
auto-injection path. `vite.config.js` therefore builds **both** entry points, while the service
provider declares only `cp.js` as a `$vite` input: the control panel loads just its own half, and
`bridge.js` sits in the same manifest, where the `ResponseCreated` listener resolves it from the
registry the addon populated at boot. No paths are hardcoded and cache busting survives.

The set **id** travels, never an index — `Replicator::performAugmentation()` filters out sets with
`enabled: false` and reindexes, so the front-end loop index does not match the array position in
the publish form.

The control-panel side depends on two internals Statamic does not guarantee
(`<fieldId>-sortable-item` and `[data-replicator-set]`). Both routes are tried in order in
`PreviewBridge.vue` and fail with a `console.warn` rather than silently. Re-check them after a
Statamic minor upgrade.

## Development

The repository carries its own DDEV environment (PHP 8.4, Node 22, no database).

```bash
ddev start
ddev composer install
ddev npm install
ddev npm run build          # after every JS change — and before every commit
ddev exec ./vendor/bin/phpunit
```

`resources/dist/` is committed: Composer ships only repository content, so without the built
bundles there would be nothing for a consumer to publish. The `hot` file that `npm run dev`
writes there is gitignored and must never ship — in a consumer it would make
`Vite::isRunningHot()` true and point both bundles at a dev server on *their* localhost.

Once the package has a release workflow, this can move to `extra.download-dist`
(`pixelfear/composer-dist-plugin`), the way `statamic/seo-pro` does it, and the committed build
goes away. The plugin is already allow-listed in `composer.json`.

## Requirements

- PHP 8.2+
- Statamic 6

## License

MIT
