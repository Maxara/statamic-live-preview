---
name: statamic-live-preview
description: Markiert Page-Builder-Blöcke für die Live-Preview-Bridge mit dem {{ lp_target }}-Tag, damit ein Klick in der Vorschau im Control Panel zum passenden Set springt. Auto-Trigger beim Anlegen oder Ändern eines Page-Builder-Blocks, eines Replicator-Sets, eines Abschnitts oder eines Block-Partials unter resources/views/page_builder/ — sowie bei „Live Preview", „lp_target", „data-lp-set", „Click-to-Edit".
---

# Live-Preview-Markup für Page-Builder-Blöcke

Das Addon `seitwerk/statamic-live-preview` hebt Blöcke in der Live Preview beim Hover hervor
und springt beim Klick im Publish-Formular zum passenden Set. Dafür braucht jeder Block **eine**
Annotation im Template. Ohne sie ist der Block in der Vorschau nicht anklickbar — und das fällt
niemandem auf, bis ein Redakteur ihn sucht.

## Die Regel

Beim Anlegen eines neuen Block-Partials: `{{ lp_target }}` an das **äußerste** Element.

```antlers
<section class="bg-paper px-5 py-12"{{ lp_target }}>
    …
</section>
```

Drei Dinge, die dabei regelmäßig schiefgehen:

- **Kein Leerzeichen vor `{{`.** Der Tag bringt sein führendes Leerzeichen selbst mit. Schreibt
  man `class="…" {{ lp_target }}`, steht am Ende ein doppeltes im Markup.
- **Genau ein Target pro Block.** Nicht zusätzlich an Kindelemente. Die Granularität ist bewusst
  der Block, nicht das Feld: Zum Block zu springen genügt der Redaktion, und jedes weitere
  Element wäre dauerhafte Pflegelast bei jedem neuen Block.
- **Nicht in ein `{{ if }}` wickeln.** Der Tag prüft selbst, ob die Live Preview aktiv ist, und
  gibt außerhalb nichts aus. Der Produktions-Footprint ist null.

## Partials, die auch außerhalb des Page Builders laufen

Hat ein Block keinen eigenen Wrapper, sondern delegiert an ein geteiltes Partial, darf dieses
**nicht** auf den Kontext zurückfallen: außerhalb der Replicator-Schleife ist `id` die Entry-id,
und die kennt das Publish-Formular nicht. Der Aufrufer reicht die Set-id explizit durch:

```antlers
{{# page_builder/_hero_image.antlers.html — hat die Set-id #}}
{{ partial:partials/hero-media :image="image" :lp_set="id" }}

{{# partials/_hero-media.antlers.html — geteilt, kennt seinen Ursprung nicht #}}
<section class="relative h-105"{{ lp_target :set="lp_set" }}>
```

Ein leer übergebener `set`-Parameter unterdrückt die Ausgabe. Der Aufrufer ohne `lp_set` — etwa
eine Standort-Detailseite — bekommt also korrekt nichts.

## Replicator-Einstellung

`collapse: accordion` am Page-Builder-Replicator (nicht `collapse: true`). Statamic öffnet damit
beim Aufklappen immer nur ein Set, und `reveal.element()` löst über das `expanded`-Event genau
dieses Verhalten aus: Ein Klick in der Vorschau öffnet den Zielblock und schließt die anderen.
Reine Konfiguration, kein Code.

## Farbe

Die Markierung ist neutrales Blau (`#4f8ef7`) — bewusst nicht die Projektpalette, damit sie als
Werkzeug lesbar bleibt. Überschreibbar per CSS-Variable `--lp-bridge-accent`, im Frontend wie im
Control Panel.

## Prüfen

Ein Render-Test über die Page-Builder-Schleife hält die Reihenfolge der `data-lp-set`-Werte
gegen die Set-ids — er fängt genau den Fall, dass ein neuer Blocktyp die Annotation vergisst:

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

Schnellprüfung auf der Kommandozeile: die Zahl der annotierten Partials gegen die Zahl der Sets
im Fieldset halten.

```bash
grep -rl "lp_target" resources/views/page_builder/ | wc -l
```
