<?php

declare(strict_types=1);

/*
 * AccessPlus
 *
 * Package: vtinnovations/accessplus
 * Copyright: V&T Innovations Team
 * Licence: LGPL-3.0-or-later
 * Website: https://www.v-t.one
 */

namespace VTInnovations\AccessPlus\Frontend;

/**
 * German explanations for the common axe-core rule ids. axe ships English-only
 * messages; this maps the rule id to a short German title + actionable hint so
 * the report reads in German. Unknown rules fall back to axe's own text.
 */
final class AxeMessages
{
    /** @var array<string, array{title: string, hint: string}> */
    private const MAP = [
        'color-contrast' => [
            'title' => 'Zu geringer Farbkontrast',
            'hint' => 'Text und Hintergrund kontrastieren zu schwach. Mindestkontrast: 4,5:1 für normalen Text, 3:1 für großen Text (ab 18pt bzw. 14pt fett). Text- oder Hintergrundfarbe anpassen.',
        ],
        'image-alt' => [
            'title' => 'Bild ohne Alternativtext',
            'hint' => 'Dem <img> fehlt ein alt-Attribut. Aussagekräftigen Alt-Text ergänzen; rein dekorative Bilder bekommen ein leeres alt="".',
        ],
        'input-image-alt' => [
            'title' => 'Bild-Schaltfläche ohne Alternativtext',
            'hint' => 'Einem <input type="image"> fehlt der Alt-Text. alt-Attribut mit der Funktion der Schaltfläche ergänzen.',
        ],
        'area-alt' => [
            'title' => 'Image-Map-Bereich ohne Alternativtext',
            'hint' => 'Einem <area> in einer Image-Map fehlt der Alt-Text. alt-Attribut ergänzen.',
        ],
        'role-img-alt' => [
            'title' => 'Element mit role="img" ohne Beschriftung',
            'hint' => 'Element mit role="img" braucht eine Textalternative (aria-label oder aria-labelledby).',
        ],
        'svg-img-alt' => [
            'title' => 'SVG-Grafik ohne Beschriftung',
            'hint' => 'Informativem SVG (role="img") eine Textalternative geben (<title> oder aria-label).',
        ],
        'link-name' => [
            'title' => 'Link ohne erkennbaren Namen',
            'hint' => 'Der Link hat keinen lesbaren Text. Sichtbaren Linktext oder aria-label ergänzen, der das Ziel beschreibt (nicht „hier klicken").',
        ],
        'button-name' => [
            'title' => 'Schaltfläche ohne Namen',
            'hint' => 'Dem Button fehlt ein zugänglicher Name. Sichtbaren Text oder aria-label ergänzen.',
        ],
        'label' => [
            'title' => 'Formularfeld ohne Beschriftung',
            'hint' => 'Eingabefeld braucht ein verknüpftes <label> (for/id) oder aria-label, damit klar ist, was einzugeben ist.',
        ],
        'form-field-multiple-labels' => [
            'title' => 'Formularfeld mit mehreren Labels',
            'hint' => 'Ein Feld ist mehrfach beschriftet. Auf genau ein eindeutiges Label reduzieren.',
        ],
        'select-name' => [
            'title' => 'Auswahlfeld ohne Beschriftung',
            'hint' => 'Dem <select> fehlt ein Label. <label> verknüpfen oder aria-label setzen.',
        ],
        'html-has-lang' => [
            'title' => 'Seitensprache fehlt',
            'hint' => 'Dem <html>-Element fehlt das lang-Attribut. Sprache der Seite setzen, z. B. lang="de".',
        ],
        'html-lang-valid' => [
            'title' => 'Ungültige Seitensprache',
            'hint' => 'Das lang-Attribut enthält keinen gültigen Sprachcode. Gültigen Code verwenden, z. B. „de".',
        ],
        'valid-lang' => [
            'title' => 'Ungültige Sprachangabe',
            'hint' => 'Ein lang-Attribut im Inhalt ist ungültig. Gültigen Sprachcode verwenden.',
        ],
        'document-title' => [
            'title' => 'Seitentitel fehlt',
            'hint' => 'Der Seite fehlt ein <title>. Aussagekräftigen Titel im <head> ergänzen.',
        ],
        'duplicate-id' => [
            'title' => 'Doppelte ID',
            'hint' => 'Eine id kommt mehrfach vor. IDs müssen eindeutig sein – doppelte umbenennen.',
        ],
        'duplicate-id-aria' => [
            'title' => 'Doppelte ID in ARIA-Referenz',
            'hint' => 'Eine per ARIA referenzierte id ist nicht eindeutig. IDs eindeutig machen.',
        ],
        'frame-title' => [
            'title' => 'iframe ohne Titel',
            'hint' => 'Dem <iframe> fehlt ein title-Attribut, das den Inhalt beschreibt. Ergänzen.',
        ],
        'th-has-data-cells' => [
            'title' => 'Tabellenkopf ohne Datenzellen',
            'hint' => 'Tabellenstruktur prüfen: Kopfzellen (<th>) müssen zu Datenzellen gehören.',
        ],
        'td-headers-attr' => [
            'title' => 'Falsche headers-Zuordnung in Tabelle',
            'hint' => 'Das headers-Attribut verweist auf nicht vorhandene Zellen. Zuordnung korrigieren.',
        ],
        'list' => [
            'title' => 'Fehlerhafte Listenstruktur',
            'hint' => '<ul>/<ol> dürfen direkt nur <li> (bzw. erlaubte Elemente) enthalten. Struktur bereinigen.',
        ],
        'listitem' => [
            'title' => 'Listeneintrag außerhalb einer Liste',
            'hint' => '<li> muss in einem <ul> oder <ol> stehen. Verschachtelung korrigieren.',
        ],
        'definition-list' => [
            'title' => 'Fehlerhafte Definitionsliste',
            'hint' => '<dl> korrekt mit <dt>/<dd> aufbauen.',
        ],
        'aria-required-attr' => [
            'title' => 'Pflicht-ARIA-Attribut fehlt',
            'hint' => 'Die ARIA-Rolle verlangt bestimmte Attribute, die fehlen. Erforderliche aria-* ergänzen.',
        ],
        'aria-required-children' => [
            'title' => 'Erforderliche ARIA-Kindelemente fehlen',
            'hint' => 'Die ARIA-Rolle erwartet bestimmte Kindrollen. Struktur entsprechend aufbauen.',
        ],
        'aria-required-parent' => [
            'title' => 'Erforderliches ARIA-Elternelement fehlt',
            'hint' => 'Das Element mit dieser ARIA-Rolle braucht ein passendes Elternelement.',
        ],
        'aria-valid-attr-value' => [
            'title' => 'Ungültiger ARIA-Attributwert',
            'hint' => 'Ein aria-*-Attribut hat einen ungültigen Wert. Korrigieren.',
        ],
        'aria-allowed-attr' => [
            'title' => 'Unzulässiges ARIA-Attribut',
            'hint' => 'Ein aria-*-Attribut ist für diese Rolle nicht erlaubt. Entfernen oder Rolle anpassen.',
        ],
        'aria-hidden-focus' => [
            'title' => 'Fokussierbares Element ist aria-hidden',
            'hint' => 'Ein per aria-hidden="true" verstecktes Element ist trotzdem fokussierbar. Aus dem Tab-Fluss nehmen.',
        ],
        'nested-interactive' => [
            'title' => 'Verschachtelte interaktive Elemente',
            'hint' => 'Interaktive Elemente (z. B. Button im Link) dürfen nicht ineinander liegen. Struktur entzerren.',
        ],
        'meta-viewport' => [
            'title' => 'Zoom unterbunden',
            'hint' => 'Die Viewport-Angabe verbietet das Zoomen (user-scalable=no/maximum-scale). Zoom zulassen.',
        ],
        'scrollable-region-focusable' => [
            'title' => 'Scrollbereich nicht per Tastatur erreichbar',
            'hint' => 'Ein scrollbarer Bereich ist nicht fokussierbar. tabindex="0" ergänzen, damit er per Tastatur scrollbar ist.',
        ],
        'heading-order' => [
            'title' => 'Überschriften-Reihenfolge springt',
            'hint' => 'Überschriftenebenen (h1–h6) nicht überspringen. Logische Reihenfolge einhalten.',
        ],
        'empty-heading' => [
            'title' => 'Leere Überschrift',
            'hint' => 'Eine Überschrift enthält keinen Text. Text ergänzen oder Überschrift entfernen.',
        ],
        'list-item' => [
            'title' => 'Listeneintrag außerhalb einer Liste',
            'hint' => '<li> muss in <ul>/<ol> stehen.',
        ],
    ];

    /**
     * @return array{title: string, hint: string}|null
     */
    public static function german(string $ruleId): ?array
    {
        return self::MAP[$ruleId] ?? null;
    }
}
