# vtinnovations/accessplus — Barrierefreiheit für Contao

*[English version](README.en.md)*

Native Barrierefreiheits-**Prüfung**, **Reparatur** und **Überwachung** direkt im
Contao-Backend. Kein aufgesetztes Overlay-Pflaster, sondern im CMS verankert:
**prüfen → erzeugen → absichern → überwachen**, plus Pflichtdokumente.
Datenhoheit beim Kunden, lauffähig von **Contao 4.13 bis 5.7+**, **PHP 8.1+**,
auch auf Plesk / Shared-Hosting ohne SSH (Contao-eigenes Cron-System, kein
Systemcron nötig).

> **Kurz gesagt:** Ein Backend-Modul „Barrierefreiheit" findet die
> a11y-Probleme deiner Seite (Datenbank **und** echtes Frontend via axe-core),
> schlägt Reparaturen vor (u. a. KI-Alt-Texte, ARIA-Namen, KI-Untertitel,
> Einfache/Leichte Sprache), liefert die BFSG-Pflichtdokumente (Erklärung +
> Meldekanal) und überwacht den Zustand fortlaufend mit einem ehrlichen Score.

> **Wichtiger Hinweis:** Das Bundle ist ein Werkzeug zur Verbesserung der
> Barrierefreiheit. Es trifft **keine** Aussage über Rechts- oder
> BFSG/BITV-Konformität und ersetzt keine fachliche/menschliche Prüfung.
> KI-Inhalte sind stets **Entwürfe** und gehen nie automatisch live.
> Das Komfort-Overlay ist eine ehrliche Beigabe, **kein** Ersatz für echte
> Barrierefreiheit.

---

## Status

Produktiv eingesetzte, native Contao-Implementierung (kein Prototyp, kein
Platzhalter). Aktuelle Version: siehe `composer.json`.

## Feature-Überblick

Ein Backend-Hub „**Barrierefreiheit**" mit Tabs bündelt alles:

| Bereich | Was es tut |
|---|---|
| **Dashboard** | Score-Ring (0–100, severity-gewichtet), Statistik-Karten, 3-Spalten-Lage (Erledigt / Ein-Klick / Manuell), Verlauf & Trend ▲▼, **ein** „Voll-Scan starten" (Datenbank **+** Frontend, mit Fortschrittsbalken, unterbrechbar & fortsetzbar) |
| **Bericht** | Alle Befunde mit deutscher Erklärung, konkretem Fix-Hinweis, „Auf der Seite zeigen" (Live-Highlight) und „Jetzt fixen" (Deep-Link in die Contao-Bearbeitungsmaske) |
| **Datenbank-Linter** | 5 regelbasierte Checks ohne Rendering: fehlende Alt-Texte, Überschriften-Hierarchie, nichtssagende Linktexte, fehlende Formular-Labels, Seitensprache |
| **Frontend-Scan** | axe-core 4.10 (lokal gebündelt, kein Remote-Loader) prüft die echten, veröffentlichten Seiten im Same-Origin-iframe — findet Kontrast-, Landmark- und DOM-Probleme, die die DB nie sieht. Nur gesetzliche Pflicht (WCAG 2.x A + AA). Wird über den Voll-Scan im Dashboard angestoßen, kein eigener Tab |
| **KI-Alt-Texte** | Vision-KI erzeugt Alt-Text-**Vorschläge** je Sprache nur für **verwendete** Bilder; Redakteur bearbeitet & übernimmt; schreibt nur in leere Meta-Slots (nie überschreiben), voll rückgängig-fähig |
| **ARIA-Namen** | Für Elemente, die der Frontend-Scan als unbenannt meldet (Links, Buttons, Frames, Formularfelder …): Redakteur prüft einen vorbefüllten Namensvorschlag und gibt frei; wird zur Laufzeit als `aria-label` gesetzt, nie über einen bestehenden Namen |
| **KI-Untertitel** | Whisper-Transkription von Audio/Video → **VTT-Entwurf** → Freigabe schreibt `.vtt` neben die Quelle und verkabelt `<track>` im Frontend automatisch |
| **Einfache / Leichte Sprache** | KI-**Entwurf** je Register (Einfache Sprache ~B1, Leichte Sprache ~A1); In-Place-Umschaltung im Frontend per Umschalter/Overlay; strenger Review, Sperren gegen Neu-Generierung, nie auto-live |
| **Overlay** | Optionales Komfort-Widget mit 30 einzeln abschaltbaren Funktionen in 4 Gruppen: **Profile** (Epilepsie/Sehbehinderung/ADHS-Voreinstellungen), **Lesen** (Schriftgröße, Lese-Font/OpenDyslexic, Zeilen-/Zeichenabstand, Lesehilfe, Bionic, Linknavigation …), **Ausrichtung** (Kontrastmodi, Graustufen, Vorlesen, Animationen stoppen, Ton stumm, großer Cursor, Fokus-/Hover-Hervorhebung, Textausrichtung …), **Farben** (Text-, Titel-, Link- und Hintergrundfarbe) |
| **Erklärung & Meldekanal** | Editor für die Barrierefreiheits-**Erklärung** (Pflichtdokument) + Frontend-Feedbackformular mit Backend-Posteingang und E-Mail-Benachrichtigung |
| **PDF-Prüfung** | Erkennt fehlenden Titel / fehlende Sprache / fehlende Tags in PDFs (Detektion + Anleitung, ohne die Datei zu verändern) |
| **Monitoring** | Wiederholt Prüfungen automatisch: CLI-Command, Contao-Cron (täglich, poor-man's-cron – kein Systemcron nötig) und optionaler Hook beim Speichern von Inhalten — gedrosselt, mit Score-Trend |
| **Audit & Undo** | Jede automatische Änderung wird protokolliert (wer/wann/vorher/nachher) und ist rückgängig machbar |
| **Lizenz** | Pro-Lizenz **je Startpunkt**, gebunden an dessen **exakten** Hostnamen; hinterlegt in den Startpunkt-Einstellungen |

---

## Funktionsstatus

Das Bundle hat **kein** Free-Kontingent: Ohne aktivierte Pro-Lizenz für den
jeweiligen Startpunkt ist Contao unverändert, alle unten stehenden Funktionen
sind aus.

| Funktion | Status |
|---|---|
| Datenbank-Linter, Frontend-Scan (axe-core), Dashboard/Score | Nur Pro |
| KI-Alt-Texte, ARIA-Namen, KI-Untertitel, Einfache/Leichte Sprache | Nur Pro, zusätzlich **Bedingt** auf einen konfigurierten KI-Zugang |
| Overlay (Komfort-Widget) | Nur Pro |
| Erklärung & Meldekanal (Frontend-Module) | Nur Pro |
| PDF-Prüfung | Nur Pro, **Bedingt** auf `smalot/pdfparser` (Composer-Pflichtabhängigkeit, immer installiert) |
| Monitoring (CLI/Cron/Save-Hook) | Nur Pro |
| Audit & Undo | Nur Pro |

---

## Installation

### Composer / Contao Manager

```bash
composer require vtinnovations/accessplus
```

Anschließend im Contao Manager bzw. per CLI die Migrationen ausführen:

```bash
vendor/bin/contao-console contao:migrate
```

Das legt die Tabellen `tl_accessplus_*` an und installiert die Frontend-Assets
(`vendor/bin/contao-console assets:install`, vom Manager automatisch).

> **Ohne `--with-deletes` migrieren**, wenn im Manager DROP-Statements anderer
> Pakete angezeigt werden, die du nicht ausführen willst — die CREATE-Statements
> dieses Bundles laufen auch ohne.

### Voraussetzungen

- Contao **4.13** oder **5.3–5.7+**
- PHP **8.1+**, PHP-Erweiterung **sodium** für die Lizenzprüfung (auf praktisch
  jedem Hoster ab PHP 8.1 bereits vorhanden)
- Für KI-Funktionen: ein **OpenAI-** oder **OpenAI-kompatibler** API-Zugang
  (eigene Basis-URL möglich). Ohne Key/Freigabe laufen alle Nicht-KI-Funktionen.

---

## Erste Schritte

1. **Seitenstruktur → Startpunkt bearbeiten → „AccessPlus Licence management"**:
   Lizenzschlüssel eintragen und **„Lizenz prüfen und aktivieren"** klicken. Ohne
   gültige Lizenz für den jeweiligen Startpunkt bleibt das Bundle inaktiv und
   Contao verhält sich exakt wie ohne Installation (siehe DOKUMENTATION B4).
2. **Backend → Barrierefreiheit → Einstellungen**: bei KI-Nutzung API-Key
   eintragen und den Schalter **„Keine externen Aufrufe"** deaktivieren
   (Standard: AN = kein KI-/Inhalts-Egress).
3. **Verbindungstest** ausführen (Probe-Call, respektiert den Egress-Schalter).
4. **Dashboard → „Voll-Scan starten"**: Datenbank-Analyse + Frontend-Scan laufen
   nacheinander, der Score erscheint.
5. Im **Bericht** die Befunde abarbeiten — Ein-Klick-Fixes übernehmen, manuelle
   per „Jetzt fixen" in der Contao-Maske korrigieren.
6. **Erklärung & Meldekanal** ausfüllen und die beiden Frontend-Module
   (Erklärung + Feedback) in einer Seite einbinden.

Ausführliche Bedienschritte für jeden Tab: [DOKUMENTATION.md](DOKUMENTATION.md), Teil A.

---

## Berechtigungen & Zugriffskontrolle

Alle Backend-Tabs liegen hinter der regulären Contao-Backend-Firewall und den
Contao-Benutzer-/Gruppenrechten für das Modul „Barrierefreiheit". Schreibende
Aktionen laufen ausschließlich über POST mit Contao-Request-Token; es gibt keine
mutierende GET-Route. Die Lizenzverwaltung liegt zusätzlich unter den
Startpunkt-Bearbeitungsrechten der Seitenstruktur.

---

## Sicherheit & Datenschutz

- **Keine Klartext-Keys.** API-Schlüssel verschlüsselt at rest (defuse);
  Encryption-Key in separater Datei (`0600`), nie in DB/VCS/Log/Frontend.
- **Egress-Kill-Switch „Keine externen Aufrufe" — standardmäßig AN.** Solange
  aktiv verlässt kein KI-/Inhalts-Datenpaket den Server. Mehrschichtig erzwungen.
- **Keine mutierende GET-Route.** Alle Schreibaktionen via POST + Request-Token,
  Backend-Firewall.
- **Datenminimierung & maskiertes Logging** bei externen Aufrufen; SSRF-Guards
  auf Basis-URLs.
- **KI-Inhalte nie auto-live** — Redakteur prüft und gibt frei; Meta-Daten werden
  nie überschrieben; alles auditiert und rückgängig machbar.

Details zum Sicherheitsmodell: [DOKUMENTATION.md](DOKUMENTATION.md), Abschnitt B7.

---

## Betriebssicherheit

- **Egress-Schalter unabhängig von der Lizenzprüfung.** „Keine externen Aufrufe"
  steuert ausschließlich KI-/Inhalts-Aufrufe; die für den Betrieb notwendige
  Lizenzprüfung ist davon nicht betroffen (siehe Lizenz-Abschnitt).
  Beim Speichern von Inhalten bricht ein optionaler Monitoring-Hook das
  Speichern selbst nie ab.
- **KI-Ausfälle blockieren nichts Bestehendes.** Ein fehlgeschlagener oder
  deaktivierter KI-Aufruf verhindert nur neue Vorschläge; bereits freigegebene
  Inhalte bleiben unangetastet.

## Laufzeitverzeichnisse

Zustand außerhalb der Datenbank liegt unter `var/accessplus/` (außerhalb des
öffentlichen Docroots, per `.gitignore` von der Versionskontrolle
ausgeschlossen): Lizenzstand je Startpunkt, Verschlüsselungs-Schlüsseldatei
(`0600`) und verschlüsselte Zugangsdaten. Details: DOKUMENTATION B6.

## Externe Kommunikation

- **Lizenzprüfung:** ausschließlich `https://www.v-t.one`, unabhängig vom
  Egress-Schalter (siehe Lizenz-Abschnitt und DOKUMENTATION B4).
- **KI-Funktionen:** an den im Einstellungen-Tab hinterlegten OpenAI- oder
  OpenAI-kompatiblen Endpunkt, nur wenn der Egress-Schalter deaktiviert ist.
- Keine weiteren externen Aufrufe, kein Remote-Tracking, axe-core lokal
  gebündelt.

---

## Technischer Rahmen

- Package `vtinnovations/accessplus`, Namespace `VTInnovations\AccessPlus\`, DB-Prefix
  `tl_accessplus_`, Zustand in `var/accessplus/`.
- Versions-Weichen ausschließlich über `Compatibility\Compat` (kein verstreutes
  `version_compare`).
- KI-Provider über eine Abstraktion: **OpenAI** + **OpenAI-kompatibel**.
- axe-core lokal gebündelt (MPL-2.0), kein externer Loader/Tracker.

### CLI-Befehle

```bash
accessplus:scan                         # Datenbank-Linter
accessplus:pdf:scan                     # PDF-Prüfung
accessplus:alt:generate                 # KI-Alt-Text-Vorschläge
accessplus:subtitles:generate --lang de --limit 5
accessplus:simplify --register einfach --limit 200
accessplus:monitor                      # Re-Scan + Stempel
```

### Qualitäts-Gates

```bash
composer install
vendor/bin/phpunit                # Unit-Tests (gemockt, kein Netz)
```

Verifiziert: `composer install` löst mit PHP 8.1 sauber auf (benötigt die
PHP-Erweiterungen `intl` und `gd` für `contao/core-bundle`/`contao/image`),
`vendor/bin/phpunit` läuft grün (93 Tests, 244 Assertions). Statische
Analyse (z. B. PHPStan) und ein Coding-Standard-Fixer (z. B. ECS) sind
**nicht** als Dev-Abhängigkeit deklariert und ohne zusätzliche eigene
Einrichtung nicht über `vendor/bin/` verfügbar.

### Cache-Leerung

Nach Konfigurationsänderungen oder Deployment die übliche Contao-/Symfony-
Cache-Leerung ausführen:

```bash
vendor/bin/contao-console cache:clear
```

---

## Tests

Die sicherheitskritischen Teile (Lizenz-Austausch, kryptografische Prüfung,
Zustandsspeicher) sind mit PHPUnit abgedeckt (`vendor/bin/phpunit`, gemockt,
ohne Netzzugriff) — verifiziert grün: 93 Tests, 244 Assertions. Details:
`tests/`.

## Fehlerbehebung & bekannte Einschränkungen

Ausführliche Fehlerbehebungstabelle und ehrliche Grenzen (z. B. Third-Party-
Slider ohne Contao-Bildtabelle, Cross-Origin-Seiten im Frontend-Scan,
PHP-Zeitlimit bei großen Untertitel-Batches): siehe
[DOKUMENTATION.md](DOKUMENTATION.md), Abschnitte B11 und B12.

---

## Lizenz

Code: **LGPL-3.0-or-later**.

Nutzungslizenz: **Pro — je Startpunkt und exaktem Hostnamen**, bezogen über
V-T.ONE. Es gibt keine Trial-, Free- oder Kulanzstufe: ohne aktivierte,
kryptografisch geprüfte Pro-Lizenz sind die Funktionen für den betreffenden
Startpunkt aus. Hinterlegt wird sie unter *Seitenstruktur → Startpunkt
bearbeiten → AccessPlus Licence management*.

`www.example.com` und `example.com` sind dabei **verschiedene** Hosts; jeder
Startpunkt braucht die Domain, mit der er in Contao konfiguriert ist.

Die Lizenzprüfung kontaktiert ausschließlich `https://www.v-t.one` — unabhängig
vom Schalter „Keine externen Aufrufe", der KI-/Inhalts-Egress steuert. Details
zu den übertragenen Daten: DOKUMENTATION B4.

Copyright © V&T Innovations Team. Website: [www.v-t.one](https://www.v-t.one).

Ausführliche Bedien- und Admin-Anleitung: siehe [DOKUMENTATION.md](DOKUMENTATION.md)
([English](DOKUMENTATION.en.md)).
