# Dokumentation — vtinnovations/accessplus

*[English version](DOKUMENTATION.en.md)*

Barrierefreiheits-Bundle für Contao. Diese Doku hat zwei Teile:

- **Teil A — Nutzerhandbuch** (Redakteur / Website-Betreiber): Wie man scannt,
  Befunde abarbeitet und Inhalte barriereärmer macht.
- **Teil B — Administratorhandbuch** (Contao-Admin / Agentur): Installation,
  Einstellungen, Lizenz, KI-Anbindung, Sicherheit, Deployment, Fehlersuche.

> **Rechtlicher Rahmen (bitte lesen):** Dieses Werkzeug hilft, Barrieren zu
> finden und zu beheben. Es trifft **keine** Aussage über die Erfüllung von BFSG,
> BITV oder EN 301 549 und ersetzt **keine** fachliche/menschliche Prüfung.
> KI-Ergebnisse sind **Entwürfe** und werden nie automatisch veröffentlicht.
> Das Overlay ist eine Komfort-Beigabe, kein Ersatz für echte Barrierefreiheit.

---

# Teil A — Nutzerhandbuch

## A1. Das Backend-Modul „Barrierefreiheit"

Alles liegt unter einem Menüeintrag mit einer Tab-Leiste, in dieser Reihenfolge:

**Dashboard · Bericht · PDF · KI-Alt-Texte · ARIA-Namen · KI-Untertitel ·
Einfache Sprache · Overlay · Erklärung · Verlauf** (+ eigenständiges Backend-
Modul **Meldungen** für den Feedback-Posteingang).

Der Frontend-Scan (axe-core) ist kein eigener Tab, sondern Teil des
„Voll-Scan starten" im Dashboard.

## A2. Dashboard

- **Score-Ring** (0–100): ehrlicher Gesamtwert aus **allen** offenen Befunden
  (Datenbank + Frontend), gewichtet nach Schwere (kritisch 4 · ernst 3 · mäßig 2
  · gering 1). Grün ab 90, Orange ab 70, sonst Rot.
- **Statistik-Karten:** offene Befunde, kritisch, ernst, Ein-Klick, manuell,
  erledigt, Frontend, Bilder ohne Alt.
- **3 Spalten:**
  - **Erledigt** — bereits behoben,
  - **Ein-Klick** — automatisch reparierbar (z. B. fehlende Alt-Texte),
  - **Manuell** — braucht redaktionelle Arbeit.
- **Verlauf & Trend** (▲ neue / ▼ behobene) über die letzten Läufe.

### Voll-Scan starten

Ein Knopf, zwei Phasen mit Fortschrittsbalken:

1. **Datenbank-Analyse** (schnell) — die 5 regelbasierten Checks.
2. **Frontend-Analyse** (langsamer, ~2–3 s/Seite) — axe-core lädt jede
   veröffentlichte Seite in ein unsichtbares iframe und prüft das echte HTML.
   Läuft in 10er-Batches mit kurzen Pausen; nach jeder Seite wird gespeichert.

**Unterbrechbar & fortsetzbar:** Bricht der Scan ab (Browser zu, Reload), bietet
das Dashboard „**Scan fortsetzen (X/Y)**" — bereits geprüfte Seiten werden
übersprungen. „Von vorn starten" setzt neu auf.

> Der Scan schaltet automatisch alle Videos/Audios der geprüften Seiten stumm
> (auch eingebettete YouTube/Vimeo u. a.), damit im Backend-Tab nichts abspielt.

## A3. Bericht — Befunde abarbeiten

Jeder Befund zeigt:

- **Deutsche Erklärung** + konkreter **Fix-Hinweis** (bei Frontend-Befunden aus
  der axe-Zusammenfassung übersetzt),
- **× N Seiten** (gleiche Ursache über mehrere Seiten zusammengefasst),
- **„Auf der Seite zeigen"** — öffnet die Seite und markiert das betroffene
  Element live mit rotem Rahmen,
- **„Jetzt fixen"** — Deep-Link direkt in die passende Contao-Bearbeitungsmaske
  (Inhaltselement / Formularfeld / Seite).

Status je Befund: **offen → bestätigt / ignoriert / behoben**. Ignorierte und
bestätigte Befunde bleiben über Scans hinweg „sticky". Findet der Frontend-Scan
ein unbenanntes Element (Link, Button, Formularfeld), taucht es zusätzlich im
Tab **ARIA-Namen** (A5) auf.

## A4. Bilder — KI-Alt-Texte

1. **KI-Alt-Texte** öffnen → „Vorschläge erzeugen". Die KI beschreibt **nur
   tatsächlich verwendete** Bilder (Galerien, Slider, Inserttags, Custom-Elemente
   werden erfasst) und erkennt dekorative Bilder.
2. Jeder Vorschlag ist **bearbeitbar** (Textfeld) — anpassen, dann **Übernehmen**
   oder **Verwerfen**.
3. Übernehmen schreibt den Alt-Text **nur in leere** Meta-Felder in `tl_files`
   (bestehende werden nie überschrieben). Dekorative Bilder erhalten leeres Alt.
4. Wird ein Alt-Text später entfernt, taucht der Vorschlag wieder auf (ohne neuen
   KI-Aufruf).

## A5. ARIA-Namen

Ergänzt die Alt-Text-Prüfung um Elemente, die **keinen** zugänglichen Namen
haben und daher für Screenreader unbenannt bleiben — Links, Buttons, Frames
und Formularfelder, die der Frontend-Scan entsprechend meldet (z. B.
Icon-Buttons ohne Text).

1. **ARIA-Namen** öffnen → die gemeldeten Elemente erscheinen mit einem
   vorbefüllten Namensvorschlag.
2. Vorschlag prüfen, bei Bedarf anpassen, **Freigeben**.
3. Der freigegebene Name wird zur Laufzeit als `aria-label` gesetzt — **nie**
   über einen bereits vorhandenen zugänglichen Namen (aria-label,
   aria-labelledby, title oder sichtbarer Text).

## A6. Videos/Audio — KI-Untertitel

1. **KI-Untertitel** öffnen → Datei wählen → „Erzeugen". Whisper transkribiert und
   legt einen **VTT-Entwurf** an (Status *ausstehend*, nie automatisch live).
2. Entwurf im Textfeld korrigieren, **Freigeben** schreibt `datei.de.vtt` neben
   die Quelle und bindet `<track>` im Frontend automatisch ein.
3. Große Dateien besser per CLI (siehe B9), da die Transkription im Backend
   in ein Ausführungs-Zeitlimit laufen kann.

## A7. Einfache & Leichte Sprache

Zwei Register: **Einfache Sprache** (~B1) und **Leichte Sprache** (~A1).

1. **Einfache Sprache** öffnen → Inhaltsumfang wählen (empfohlen: „★ Alle Inhalte
   (ganze Website)") → „Erzeugen". Die KI liefert **Entwürfe** je Inhaltselement.
2. Original ↔ Entwurf nebeneinander prüfen, bearbeiten, **Freigeben** (oder alle
   freigeben). **Sperren** 🔒 hält einen Text live und verhindert Neu-Generierung.
3. Im Frontend schaltet ein Umschalter (Overlay / Floating-Button / Nav-Link,
   im Backend wählbar) den Text **an Ort und Stelle** um. Ein Cookie umgeht dabei
   den Seiten-Cache.

> Guardrail: „KI-Entwurf für Leichte Sprache" — keine zertifizierte Leichte
> Sprache; menschliche Prüfung nötig.

## A8. Overlay (Komfort-Widget)

Optionales Frontend-Widget mit **30 einzeln abschaltbaren** Funktionen in
4 Gruppen (Backend-Tab **Overlay**):

| Gruppe | Funktionen |
|---|---|
| **Profile** (3) | Voreinstellungen für Epilepsie, Sehbehinderung, ADHS — schalten mehrere Einzelfunktionen gebündelt |
| **Lesen** (10) | Textgröße, Zeilenabstand, Zeichenabstand, Lese-Font, OpenDyslexic-Font, Überschriften hervorheben, Links hervorheben, Bionic-Lesen, Linknavigation |
| **Ausrichtung** (13) | Dunkel-/Hell-/Hochkontrast (wechselseitig exklusiv), Graustufen, Animationen stoppen, Ton stummschalten, großer Cursor, Bilder ausblenden, Lesehilfe (Leiste folgt der Maus), Vorlesen, Fokus-Hervorhebung, Hover-Hervorhebung, Textausrichtung |
| **Farben** (4) | Text-, Titel-, Link- und Hintergrundfarbe |

Design (Akzentfarbe des Auslösers, Position: unten/oben × rechts/links/mittig)
ist global; die Aktivierung selbst ist **je Startpunkt** schaltbar. Das Widget
ist selbst tastatur- und ARIA-korrekt (Fokus-Rückgabe, ESC, `aria-pressed`),
speichert Präferenzen nur lokal im Browser (`localStorage`) und lädt keine
externen Ressourcen.

## A9. Erklärung & Meldekanal (Pflichtdokumente)

- **Erklärung**-Tab: Editor für die Barrierefreiheits-Erklärung. Ein
  Status-Vorschlag wird aus den offenen Befunden abgeleitet (ohne Rechtsberatung).
- **Meldekanal:** Frontend-Feedbackformular (Honeypot + Token + serverseitige
  Prüfung). Eingänge landen im Backend-Posteingang **Meldungen** (Status
  neu/in Arbeit/erledigt) und lösen eine E-Mail an den hinterlegten Empfänger aus.

Beide als Frontend-Module (`accessplusStatement`, `accessplusFeedback`) in eine Seite
einbinden.

## A10. PDF-Prüfung

Erkennt in verlinkten PDFs fehlenden **Titel**, fehlende **Sprache** und
fehlende **Tags** (dreiwertig: vorhanden / fehlt / unbekannt bei komprimiert).
Das Bundle **verändert keine PDFs** — es liefert Detektion + Anleitung.

---

# Teil B — Administratorhandbuch

## B1. Installation

```bash
composer require vtinnovations/accessplus
vendor/bin/contao-console contao:migrate      # legt tl_accessplus_* an
```

Der Contao Manager übernimmt `assets:install`. Auf **Contao 4.13** müssen
`system/logs`, `system/themes/flexible` etc. echte Symlinks sein (nicht als
echte Ordner angelegt), sonst schlägt die Manager-Installation fehl — das ist
eine Site-Eigenheit, kein Bundle-Fehler.

## B2. Systemvoraussetzungen

| | |
|---|---|
| Contao | 4.13 **oder** 5.3–5.7+ |
| PHP | 8.1+ (keine 8.2-only-Syntax nötig) |
| PHP-Erweiterung | `sodium`, für die Lizenzprüfung |
| Hosting | Plesk / Shared-Hosting ohne SSH möglich |
| KI (optional) | OpenAI oder OpenAI-kompatibel (eigene Basis-URL) |
| PDF (optional) | `smalot/pdfparser` (Composer-Abhängigkeit) |
| Untertitel | Whisper-fähiger Endpunkt (`openai`/`compatible`) |

## B3. Einstellungen-Tab

- **KI-Provider:** `openai` oder `compatible` (kompatible Basis-URL angeben).
- **API-Key:** wird verschlüsselt gespeichert; die UI zeigt nur „gesetzt/leer".
- **„Keine externen Aufrufe"** (Egress-Schalter): **Standard AN**. Für KI-Aufrufe
  deaktivieren. Solange AN, verlässt kein **KI-/Inhalts**-Paket den Server. Die
  Lizenzprüfung (nur `www.v-t.one`, siehe B4) ist davon ausgenommen, weil sie
  Voraussetzung des Produkts ist.
- **WCAG-Ziel:** steuert die axe-Regel-Tags (Pflicht = WCAG 2.x A + AA).
- **Sprachen:** aktive Sprachen für Alt-Texte / Untertitel / Einfache Sprache.
- **Monitoring:** „beim Speichern prüfen" + Drossel-Intervall.
- **Verbindungstest:** einzelner Probe-Call, respektiert den Egress-Schalter.

## B4. Lizenz (je Startpunkt)

**Wo:** *Seitenstruktur → Startpunkt bearbeiten → Abschnitt „AccessPlus Licence
management"* (direkt über dem Zugriffsschutz). Das ist die **einzige** Stelle;
im Einstellungen-Tab gibt es bewusst keine zweite Lizenzverwaltung.

### Modell

- **Pro-only.** Es gibt **keine** Trial-, Free-, Kulanz- oder Grace-Stufe und
  keinen „läuft schon irgendwie"-Zustand. Ohne aktivierte, gültige Pro-Lizenz
  sind für den betreffenden Startpunkt **alle** Funktionen des Bundles aus —
  Contao verhält sich dann exakt so, als wäre es nicht installiert.
- **Eine Lizenz je Startpunkt (tl_page type=root).** Der Zustand wird pro
  Startpunkt gespeichert; eine Lizenz aktiviert **nie** einen anderen Startpunkt.
- **Exakte Hostbindung.** `example.com`, `www.example.com` und
  `shop.example.com` sind **drei verschiedene** Hosts. Es gibt keine
  eTLD+1-Zusammenfassung, kein Wildcard, keine Vererbung auf Subdomains und
  keine Alias-/CNAME-Auflösung. Maßgeblich ist die im Startpunkt konfigurierte
  Domain (Feld **Domain** im Abschnitt Routing) — **nicht** der Host aus dem
  Request-Header.
- **Ohne konfigurierte Domain keine Aktivierung.** Ein Startpunkt mit leerem
  `dns`-Feld kann nicht lizenziert werden; die Meldung im Abschnitt sagt das.

### Bedienung

| Schaltfläche | Was passiert |
|---|---|
| **Lizenz prüfen und aktivieren** | Sendet den eingegebenen Schlüssel serverseitig an V-T.ONE, prüft die signierte Antwort vollständig und speichert sie atomar. Erst danach sind die Funktionen aktiv. |
| **Lizenz aktualisieren** | Holt den aktuellen Stand (Verlängerung, geänderte Domains, Widerruf) mit dem gespeicherten Schlüssel. Schlägt das fehl, bleibt der bisherige gültige Stand unverändert erhalten. |
| **Lizenz entfernen** | Löscht den Lizenzstand dieses Startpunkts (Bestätigung erforderlich). Inhalte, Befunde, Entwürfe und Einstellungen bleiben unangetastet. |

Der Schlüssel wird nie wieder angezeigt und verlässt den Server nur Richtung
V-T.ONE. Die Bedienung ist reines Contao-Formular (POST + Request-Token +
Seitenrechte), ohne eigenes JavaScript.

### Widerruf und Domain-Umzug

Ein Lizenzstand kann auch **entzogen** werden. V-T.ONE signiert dann einen
`revoked`- (bzw. `expired`-) Zustand und liefert ihn wie jede andere
Aktualisierung aus — die äußere Operation bleibt `license_update`, es gibt
keinen separaten „Deaktivieren"-Befehl. Der Client trennt *Paket-Echtheit*
(„stammt das von V-T.ONE?") von *Berechtigung* („was darf dieser Startpunkt
jetzt?"): ein vollständig echtes Paket kann trotzdem „kein Pro mehr" bedeuten.
Ein entzogener Zustand deaktiviert die Bundle-Funktionen für diesen Startpunkt;
Contao verhält sich wieder wie im Standard.

- **Push:** V-T.ONE sendet den signierten negativen Zustand per
  `POST /rest/api/v1/accessplus-license-updater` für den betroffenen Host. Er
  wird als dauerhafter *Grabstein* gespeichert, der **Lizenz entfernen** und
  das manuelle Zurückspielen einer alten `state.json` übersteht — ein
  widerrufener Startpunkt wird nicht dadurch wieder Pro, dass eine alte
  Lizenzdatei zurückgelegt wird. Nur ein echter, neuerer signierter Stand von
  V-T.ONE setzt ihn wieder ein.
- **Domain-Umzug (A → B):** dieselbe autoritative Version geht an A als
  `revoked` und an B als `valid`; A stoppt, B startet, andere Startpunkte
  bleiben unberührt.
- **Lease-Fallback:** jeder geschützte Zustand trägt zusätzlich signierte
  Felder `license_refresh_required_at` / `license_grace_until`. Kommt ein Push
  nie an (Installation offline/abgeschottet), bestätigt ein stündlicher Cron
  den Zustand neu, sobald die Refresh-Frist überschritten ist; nach dem
  signierten Grace-Ende fallen die geschützten Funktionen geschlossen aus, bis
  ein frischer gültiger Zustand vorliegt. Eine vorübergehende Netzwerkstörung
  wird nur bis zu diesem Zeitpunkt toleriert.

### Was ohne Lizenz passiert

| Bereich | Verhalten ohne gültige Lizenz |
|---|---|
| Backend-Hub „Barrierefreiheit" | Hinweis statt Werkzeugen; nur lizenzierte Startpunkte sind wählbar |
| Voll-Scan / Datenbank-Analyse / ARIA-Ingest / Vorschau | HTTP 403, keine Datenänderung |
| Datenbank-Linter | schreibt keine Befunde für unlizenzierte Startpunkte und ändert deren vorhandene Befunde nicht |
| CLI-Commands, Cron, Monitoring | brechen mit Hinweis ab |
| Overlay, Einfache Sprache, Untertitel-`<track>`, ARIA-Injektion | keine Ausgabe, Contao-Standardseite |
| Frontend-Module (Erklärung, Meldekanal, Umschalter) | rendern nichts |
| Meldungen-Posteingang | nur lesbar — eingegangene Meldungen werden nie versteckt oder gelöscht |

### Datenübertragung (Transparenz)

Die Lizenzkommunikation geht **ausschließlich** an `https://www.v-t.one` (feste
Adresse, per Konfiguration nicht umleitbar, TLS-Prüfung an, Weiterleitungen
aus). Sie ist Voraussetzung des Produkts und daher **nicht** vom Schalter
„Keine externen Aufrufe" abhängig — dieser steuert den KI-/Inhalts-Egress.

Bei einer Aktivierung oder Aktualisierung werden der eingegebene
Lizenzschlüssel und die Domain des Startpunkts übertragen, ausschließlich bei
einem Klick im Backend. Ein anonymes Nutzungssignal (Produkt + Domain) wird
höchstens einmal pro Aufruf gesendet, in dem das Bundle für eine lizenzierte
Domain aktiv war. Beim Öffnen des Lizenzabschnitts bzw. des Hubs wird der
Lizenzstand einmal pro angemeldeter Backend-Sitzung und Startpunkt geprüft.
V-T.ONE kann außerdem serverseitig einen aktualisierten Lizenzstand
zustellen; eingehende Zustellungen werden vollständig kryptografisch geprüft
und ohne gültige Prüfung verworfen.

In gewöhnlichen Logs landen ausschließlich Vorgang, generische
Ergebnis-Kategorie, HTTP-Status, Dauer und Lizenzversion — nie die
übertragenen Datenpakete selbst oder deren Authentifizierungsmaterial.

### Betrieb

- Der Lizenzstand wird pro Startpunkt privat außerhalb des Docroots unter
  `var/accessplus/` gespeichert (in `.gitignore`) und ist nicht zwischen
  Installationen kopierbar — er ist an den exakten Host gebunden und wird bei
  jeder Prüfung erneut kryptografisch verifiziert.
- Benötigt die PHP-Erweiterung `sodium` für die Signaturprüfung. Fehlt sie,
  meldet der Abschnitt das im Klartext und aktiviert nichts.
- Zugestellte Aktualisierungen werden serverseitig gegen ein internes
  Wiedereinspiel-Gedächtnis geprüft. Bei mehreren Knoten (Load Balancing)
  muss `var/` zwischen den Knoten geteilt sein.

## B5. KI-Anbindung

Alle KI-Funktionen laufen über eine Provider-Abstraktion — **OpenAI** oder
**OpenAI-kompatibel** (eigene Basis-URL, z. B. selbst gehostetes Modell):

- **Alt-Texte:** Vision-Modell (Bild als Data-URI im Request).
- **Untertitel:** Whisper-Endpunkt (`/audio/transcriptions`, multipart, VTT).
- **Einfache/Leichte Sprache:** Text-Vervollständigung, Plaintext-Ausgabe,
  Längen-Budget gegen zu lange Umschreibungen.

Kein Aufruf ohne deaktivierten Egress-Schalter. Keys nur aus dem verschlüsselten
Store.

## B6. Datenmodell (Tabellen)

| Tabelle | Inhalt |
|---|---|
| `tl_accessplus_finding` | Befunde (DB + Frontend), Fingerprint-dedupliziert, Occurrences, sampleUrl |
| `tl_accessplus_run` | Voll-Analyse-Snapshots (Score, Zeitpunkt) |
| `tl_accessplus_altsuggestion` | Alt-Text-Vorschläge (pending/approved/rejected) |
| `tl_accessplus_track` | Untertitel-Entwürfe (VTT, Status, Provider/Modell) |
| `tl_accessplus_simplification` | Einfache/Leichte-Sprache-Entwürfe (Register, Sperre) |
| `tl_accessplus_feedback` | Meldekanal-Eingänge (neu/in Arbeit/erledigt) |
| `tl_accessplus_audit` | Append-only-Protokoll aller automatischen Änderungen (Undo) |

Zustand außerhalb der DB: `var/accessplus/config.json`,
`var/accessplus/secret.key` (0600), `var/accessplus/secrets.json`.

## B7. Sicherheit

- Keys verschlüsselt at rest (defuse), Encryption-Key separat (0600), nie in
  DB/Log/Frontend.
- Egress-Kill-Switch mehrschichtig (Client + Tester), Default AN.
- Schreibaktionen nur POST + Request-Token, Backend-Firewall; SSRF-Guards auf
  Basis-URLs; Datenminimierung + maskiertes Logging.
- Frontend-Formulare: Honeypot + Token (per Insert-Tag, cache-sicher) +
  serverseitige Validierung, Escaping.
- KI-Golden-Rule: Generieren **schreibt nie** live — nur Freigabe schreibt, und
  Meta-Daten werden nie überschrieben.
- Lizenzzustand wird bei jeder Prüfung erneut vollständig kryptografisch
  verifiziert, nicht nur einmalig beim Speichern.

## B8. Monitoring

Drei Auslöser für Wiederholungsprüfungen, ohne eigenen Systemcron zu
verlangen:

- **CLI:** `accessplus:monitor`
- **Contao-Cron:** täglich (`contao.cronjob`, interval=daily) — läuft über
  Contaos eigenes „Poor-man's-Cron" (wird durch Frontend-Aufrufe ausgelöst),
  daher auch auf Hosting ohne echten Systemcron nutzbar.
- **Beim Speichern:** Hook auf `tl_content` / `tl_page` / `tl_form_field`
  (optional, gedrosselt über ein konfigurierbares Intervall; bricht das
  Speichern nie ab).

Der Frontend-axe-Scan läuft **nicht** automatisch (nur im Browser möglich).

## B9. CLI-Befehle

```bash
accessplus:scan                         # Datenbank-Linter
accessplus:pdf:scan                     # PDF-Prüfung
accessplus:alt:generate                 # Alt-Text-Vorschläge
accessplus:subtitles:generate --lang de --limit 5
accessplus:simplify --register einfach --limit 200
accessplus:monitor                      # Re-Scan + Stempel
```

Für große Video-/Sprach-Batches die CLI nutzen (umgeht Web-Timeouts).

## B10. Deployment-Hinweise (wichtig auf Plesk!)

- **`public/bundles/` sind auf Plesk KOPIEN, kein Symlink.** Ein manueller
  Datei-Upload aktualisiert `vendor/` + `composer.json` (Manager zeigt neue
  Version), **aber nicht** `public/bundles/vtinnovationsaccessplus/*.js/css`. Dann wird
  altes JS ausgeliefert.
- **Abhilfe:** Inhalt von `Resources/public/` manuell nach
  `public/bundles/vtinnovationsaccessplus/` kopieren **und Cache leeren**.
- **Diagnose:** Direkt-URL `/bundles/vtinnovationsaccessplus/accessplus-fullscan.js` öffnen
  und Dateigröße/Zeitstempel mit `Resources/public/accessplus-fullscan.js` vergleichen.
  Das `?v=`-Suffix bustet nur den Browser-Cache, nicht die veraltete Server-Kopie.

## B11. Fehlersuche

| Symptom | Ursache / Lösung |
|---|---|
| Voll-Scan „HTTP 400" | Veraltetes `accessplus-fullscan.js` (Server-Kopie). Assets neu kopieren + Cache leeren (siehe B10). |
| Scan lädt Seiten über `http://` | Root-Seite „Verschlüsselung erzwingen" (useSSL) aktivieren — dann emittiert Contao `https`-URLs. |
| Frontend-Score „keine Analyse" | Mind. einen Voll-Scan **oder** „Nur Datenbank-Analyse" laufen lassen; Lizenz für die Domain prüfen. |
| Hub zeigt „keine gültige Lizenz" | Lizenz im **Startpunkt** hinterlegen (B4). Häufigste Ursache: der Startpunkt hat keine oder eine andere Domain als die lizenzierte (`www.` zählt separat). |
| „Die hinterlegte Lizenz muss einmalig aktualisiert werden" | Älterer Lizenzstand, noch nicht im aktuellen Format — einmal **Lizenz aktualisieren** klicken. |
| „Krypto-Erweiterung fehlt" | `sodium`-Erweiterung beim Hoster aktivieren; ohne sie ist keine Signaturprüfung und damit keine Aktivierung möglich. |
| „Erledigt" springt bei jedem Scan | Behoben: Frontend-Befunde werden nur bei **voller** Seitenabdeckung als behoben markiert (X-Frame-Options / CSP müssen `SAMEORIGIN` erlauben). |
| Falsche Kontrast-Befunde | Iframe scrollt jetzt durch (Reveal-Animationen feuern); alte False Positives per „Frontend-Befunde zurücksetzen" entfernen. |
| Untertitel unsichtbar im FE | Bundle injiziert `<track>` automatisch bei `ce_player`; VTT muss `datei.<lang>.vtt` neben der Quelle liegen (Freigabe erledigt das). |
| KI-Aufruf passiert nicht | Egress-Schalter „Keine externen Aufrufe" ist noch AN, oder kein/ungültiger API-Key. |

## B12. Grenzen (ehrlich)

- Third-Party-Slider mit **eigener** Bild-Tabelle (kein Contao-`multiSRC`) werden
  vom DB-Alt-Check nicht erfasst — nur der Frontend-axe-Scan fängt sie.
- Cross-Origin-Seiten (andere Domain) können nicht im iframe gescannt werden.
- Untertitel-Transkription im Backend kann bei großen Dateien ins PHP-Zeitlimit
  laufen → CLI verwenden.
- Custom-/RSCE-Detailseiten sind nicht auto-discoverbar; nur ihre Listing-Seite
  wird gescannt (Template-Probleme kommen darüber trotzdem hoch).

---

## Anhang — Backend-Tabs auf einen Blick

`Dashboard` (Score + Voll-Scan inkl. Frontend-Scan) · `Bericht` (Befunde) ·
`PDF` · `KI-Alt-Texte` · `ARIA-Namen` · `KI-Untertitel` · `Einfache Sprache` ·
`Overlay` · `Erklärung` · `Verlauf` (Audit/Undo).

Eigenständiges Backend-Modul: `Meldungen` (Feedback-Posteingang).

Die **Lizenz** liegt nicht im Hub, sondern beim jeweiligen Startpunkt
(*Seitenstruktur → Startpunkt bearbeiten → AccessPlus Licence management*).
