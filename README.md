# Live Preview Bridge

Click-to-Edit für die Statamic Live Preview: Hover hebt einen Page-Builder-Block in der Vorschau
hervor, ein Klick springt im Control Panel zum passenden Set — aufgeklappt, richtiger Tab,
markiert.

Statamic 6 bringt kein Visual Editing mit, aber alle Primitiven dafür. Dieses Addon verbindet
sie: `{{ live_preview }}` als Guard, die stabile Replicator-Set-`id`, ein Same-Origin-iframe und
`reveal.element()`.

## Installation

Das Paket liegt noch in keiner Registry und wird über ein `path`-Repository eingebunden. In der
`composer.json` des Projekts:

```json
"repositories": [
    { "type": "path", "url": "../statamic-live-preview" }
]
```

```bash
composer require seitwerk/statamic-live-preview "*@dev"
```

Die Assets werden von `statamic:install` automatisch nach `public/vendor/statamic-live-preview/`
publiziert — das läuft bei jedem `composer install` über `post-autoload-dump`. Manuell:

```bash
php artisan vendor:publish --tag=statamic-live-preview
```

> **Hinweis:** Ein `path`-Repository ist maschinenlokal. Bevor ein Projekt mit CI oder mehreren
> Entwicklern das Paket einbindet, braucht es ein Remote.

## Verwendung

Ein `{{ lp_target }}` an das äußerste Element jedes Block-Partials — ohne Leerzeichen davor, der
Tag bringt seins selbst mit:

```antlers
<section class="bg-paper px-5 py-12"{{ lp_target }}>
    …
</section>
```

Der Tag liest die Set-`id` aus dem Kontext der Replicator-Schleife und gibt außerhalb der Live
Preview **nichts** aus. Der Produktions-Footprint ist null.

### Geteilte Partials

Ein Partial, das auch außerhalb des Page Builders gerendert wird, darf nicht auf den Kontext
zurückfallen — dort wäre `id` die Entry-id. Der Aufrufer reicht die Set-id explizit durch, ein
leerer Wert unterdrückt die Ausgabe:

```antlers
{{ partial:partials/hero-media :lp_set="id" }}       {{# im Page Builder #}}
<section{{ lp_target :set="lp_set" }}>               {{# im geteilten Partial #}}
```

### Empfohlene Replicator-Einstellung

`collapse: accordion` statt `collapse: true`. Statamic öffnet damit beim Aufklappen immer nur ein
Set — ein Klick in der Vorschau öffnet den Zielblock und schließt die anderen.

### Farbe

Neutrales Blau (`#4f8ef7`), bewusst nicht aus einer Projektpalette, damit die Markierung als
Werkzeug lesbar bleibt. Überschreibbar per `--lp-bridge-accent` — im Frontend über das
Stylesheet der Site, im Control Panel über ein eigenes CP-Stylesheet.

## Claude-Skill

Das Paket bringt einen Claude-Code-Skill mit, der beim Anlegen neuer Page-Builder-Blöcke
automatisch das richtige Markup verwendet. Bewusst **nicht** automatisch installiert — ein Addon
schreibt nicht ungefragt in fremde Tool-Konfiguration:

```bash
php artisan vendor:publish --tag=statamic-live-preview-skill
```

Landet unter `.claude/skills/live-preview/`.

## Entwicklung

Der Ordner bringt seine eigene DDEV-Umgebung mit (PHP 8.4, Node 22, keine Datenbank).

```bash
ddev start
ddev composer install
ddev npm install
ddev npm run build          # nach jeder JS-Änderung — und vor jedem Commit
ddev exec ./vendor/bin/phpunit
```

**`resources/dist/` wird committet.** Composer liefert nur Repo-Inhalt aus; ohne gebaute Bundles
gäbe es im Zielprojekt nichts zu publishen. Sobald das Paket ein Remote mit Releases hat, kann
das auf `extra.download-dist` (`pixelfear/composer-dist-plugin`) umgestellt werden — so macht es
`statamic/seo-pro` —, dann fällt der Schritt weg. Das Plugin ist in der `composer.json` bereits
allow-gelistet.

## Aufbau

| Datei | Rolle |
| --- | --- |
| `src/Tags/Target.php` | `{{ lp_target }}` — schreibt `data-lp-set` |
| `src/Listeners/InjectBridge.php` | hängt `bridge.js` an die Vorschau-Response |
| `resources/js/bridge.js` | Overlay und Klick im iframe, `postMessage` ans CP |
| `resources/js/PreviewBridge.vue` | CP-Seite: id → Array-Pfad, `reveal.element()` |

`vite.config.js` baut **beide** Entrypoints, aber nur `cp.js` steht im `$vite`-Input des
Providers: Das Control Panel lädt nur seinen Teil, `bridge.js` liegt im selben Manifest und wird
vom Listener daraus aufgelöst.

Die CP-Seite hängt an zwei nicht öffentlich zugesagten Interna (`<fieldId>-sortable-item` und
`[data-replicator-set]`). Beide Wege stehen gestaffelt in `PreviewBridge.vue` und scheitern mit
einer `console.warn` statt still. Bei einem Statamic-Minor-Update gegenprüfen.
