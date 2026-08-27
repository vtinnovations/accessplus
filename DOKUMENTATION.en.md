# Documentation — vtinnovations/accessplus

*[Deutsche Version](DOKUMENTATION.md)*

Accessibility bundle for Contao. This documentation has two parts:

- **Part A — User guide** (editor / site operator): how to scan, work through
  findings, and make content more accessible.
- **Part B — Administrator guide** (Contao admin / agency): installation,
  settings, licence, AI connection, security, deployment, troubleshooting.

> **Legal context (please read):** This tool helps find and fix barriers. It
> makes **no** statement about compliance with the EAA, BFSG, BITV or EN 301
> 549, and does **not** replace expert/human review. AI results are
> **drafts** and are never published automatically. The overlay is a comfort
> addition, not a substitute for genuine accessibility.

---

# Part A — User guide

## A1. The "Accessibility" backend module

Everything lives under one menu entry with a tab bar, in this order:

**Dashboard · Report · PDF · AI alt text · ARIA names · AI subtitles ·
Plain language · Overlay · Statement · History** (+ a separate backend module
**Reports** for the feedback inbox).

The frontend scan (axe-core) is not a separate tab; it runs as part of
"Start full scan" on the Dashboard.

## A2. Dashboard

- **Score ring** (0–100): an honest overall value from **all** open findings
  (database + frontend), weighted by severity (critical 4 · serious 3 ·
  moderate 2 · minor 1). Green from 90, orange from 70, otherwise red.
- **Stat cards:** open findings, critical, serious, one-click, manual, done,
  frontend, images without alt text.
- **3 columns:**
  - **Done** — already fixed,
  - **One-click** — automatically fixable (e.g. missing alt text),
  - **Manual** — needs editorial work.
- **History & trend** (▲ new / ▼ fixed) across the last runs.

### Start full scan

One button, two phases with a progress bar:

1. **Database analysis** (fast) — the 5 rule-based checks.
2. **Frontend analysis** (slower, ~2–3 s/page) — axe-core loads every
   published page into an invisible iframe and checks the real HTML. Runs in
   batches of 10 with short pauses; saves after every page.

**Interruptible & resumable:** if the scan is interrupted (browser closed,
reload), the Dashboard offers "**Resume scan (X/Y)**" — pages already checked
are skipped. "Start over" resets it.

> The scan automatically mutes every video/audio element on the pages it
> checks (including embedded YouTube/Vimeo etc.), so nothing plays in the
> backend tab.

## A3. Report — working through findings

Every finding shows:

- a **plain-language explanation** + a concrete **fix hint** (translated from
  the axe summary for frontend findings),
- **× N pages** (the same root cause grouped across multiple pages),
- **"Show on page"** — opens the page and highlights the affected element
  live with a red outline,
- **"Fix now"** — a deep link straight into the matching Contao edit screen
  (content element / form field / page).

Status per finding: **open → confirmed / ignored / fixed**. Ignored and
confirmed findings stay "sticky" across scans. If the frontend scan finds an
unnamed element (link, button, form field), it also appears in the **ARIA
names** tab (A5).

## A4. Images — AI alt text

1. Open **AI alt text** → "Generate suggestions". The AI describes only
   images that are actually **used** (galleries, sliders, insert tags, custom
   elements are all captured) and recognises decorative images.
2. Every suggestion is **editable** (text field) — adjust it, then **Apply**
   or **Discard**.
3. Applying writes the alt text **only into empty** meta fields in
   `tl_files` (existing ones are never overwritten). Decorative images get an
   empty alt attribute.
4. If an alt text is later removed, the suggestion reappears (without a new
   AI call).

## A5. ARIA names

Complements the alt-text check with elements that have **no** accessible
name and therefore stay unnamed for screen readers — links, buttons, frames
and form fields that the frontend scan reports accordingly (e.g. icon-only
buttons without text).

1. Open **ARIA names** — reported elements appear with a prefilled name
   suggestion.
2. Review the suggestion, adjust it if needed, **approve**.
3. The approved name is applied at runtime as `aria-label` — **never** over
   an accessible name that already exists (aria-label, aria-labelledby,
   title, or visible text).

## A6. Video/audio — AI subtitles

1. Open **AI subtitles** → choose a file → "Generate". Whisper transcribes it
   and creates a **VTT draft** (status *pending*, never live automatically).
2. Correct the draft in the text field; **Approve** writes `file.en.vtt`
   next to the source and wires up `<track>` in the frontend automatically.
3. For large files, prefer the CLI (see B9), since backend transcription can
   run into an execution time limit.

## A7. Plain & easy language

Two registers: **plain language** (~B1) and **easy language** (~A1).

1. Open **Plain language** → choose the content scope (recommended: "★ All
   content (entire site)") → "Generate". The AI delivers **drafts** per
   content element.
2. Review original vs. draft side by side, edit, **approve** (or approve
   all). **Lock** 🔒 keeps a text live and prevents regeneration.
3. In the frontend, a toggle (overlay / floating button / nav link,
   selectable in the backend) switches the text **in place**. A cookie
   bypasses the page cache for this.

> Guardrail: "AI draft for easy language" — not certified easy language;
> human review is required.

## A8. Overlay (comfort widget)

Optional frontend widget with **30 individually switchable** features across
4 groups (backend tab **Overlay**):

| Group | Features |
|---|---|
| **Profiles** (3) | Presets for epilepsy, low vision, ADHD — each toggles a bundle of individual features |
| **Reading** (10) | Text size, line spacing, letter spacing, readable font, OpenDyslexic font, highlight headings, highlight links, bionic reading, link navigation |
| **Orientation** (13) | Dark/light/high contrast (mutually exclusive), grayscale, stop animations, mute sound, big cursor, hide images, reading guide (bar follows the mouse), text-to-speech, focus highlight, hover highlight, text alignment |
| **Colors** (4) | Text, title, link and background color |

Design (trigger accent color, position: bottom/top × right/left/middle) is
global; activation itself is switchable **per root page**. The widget is
itself keyboard- and ARIA-correct (focus return, ESC, `aria-pressed`), stores
preferences only locally in the browser (`localStorage`), and loads no
external resources.

## A9. Statement & feedback channel (mandatory documents)

- **Statement** tab: editor for the accessibility statement. A status
  suggestion is derived from the open findings (without legal advice).
- **Feedback channel:** a frontend feedback form (honeypot + token + server-
  side validation). Submissions land in the backend **Reports** inbox (status
  new/in progress/done) and trigger an email to the configured recipient.

Embed both as frontend modules (`accessplusStatement`, `accessplusFeedback`)
on a page.

## A10. PDF check

Detects a missing **title**, missing **language** and missing **tags** in
linked PDFs (three-valued: present / missing / unknown when compressed). The
bundle **never modifies PDFs** — it provides detection + guidance.

---

# Part B — Administrator guide

## B1. Installation

```bash
composer require vtinnovations/accessplus
vendor/bin/contao-console contao:migrate      # creates tl_accessplus_*
```

The Contao Manager handles `assets:install`. On **Contao 4.13**,
`system/logs`, `system/themes/flexible` etc. must be real symlinks (not
created as real folders), otherwise the Manager installation fails — that is
a site quirk, not a bundle defect.

## B2. System requirements

| | |
|---|---|
| Contao | 4.13 **or** 5.3–5.7+ |
| PHP | 8.1+ (no 8.2-only syntax required) |
| PHP extension | `sodium`, for licence verification |
| Hosting | Plesk / shared hosting without SSH is possible |
| AI (optional) | OpenAI or OpenAI-compatible (custom base URL) |
| PDF (optional) | `smalot/pdfparser` (Composer dependency) |
| Subtitles | a Whisper-capable endpoint (`openai`/`compatible`) |

## B3. Settings tab

- **AI provider:** `openai` or `compatible` (specify the compatible base
  URL).
- **API key:** stored encrypted; the UI only shows "set/empty".
- **"No external calls"** (egress switch): **ON by default**. Disable it for
  AI calls. While ON, no **AI/content** packet leaves the server. Licence
  verification (only `www.v-t.one`, see B4) is exempt from this because it is
  a prerequisite of the product.
- **WCAG target:** controls the axe rule tags (mandatory scope = WCAG 2.x A +
  AA).
- **Languages:** active languages for alt text / subtitles / plain language.
- **Monitoring:** "check on save" + throttle interval.
- **Connection test:** a single probe call that respects the egress switch.

## B4. Licence (per root page)

**Where:** *Site structure → edit root page → section "AccessPlus Licence
management"* (directly above access protection). This is the **only**
place; the Settings tab deliberately has no second licence manager.

### Model

- **Pro only.** There is **no** trial, free, grace or "somehow still works"
  tier. Without an activated, valid Pro licence, **all** of the bundle's
  features are off for the respective root page — Contao then behaves
  exactly as if it were not installed.
- **One licence per root page (`tl_page` type=root).** State is stored per
  root page; a licence **never** activates another root page.
- **Exact host binding.** `example.com`, `www.example.com` and
  `shop.example.com` are **three different** hosts. There is no eTLD+1
  collapsing, no wildcard, no inheritance to subdomains, and no alias/CNAME
  resolution. The domain configured on the root page (field **Domain** under
  Routing) is authoritative — **not** the host from the request header.
- **No activation without a configured domain.** A root page with an empty
  `dns` field cannot be licensed; the section's message states this.

### Operation

| Button | What happens |
|---|---|
| **Check and activate licence** | Sends the entered key to V-T.ONE server-side, fully verifies the signed response, and stores it atomically. Only then do the features become active. |
| **Refresh licence** | Fetches the current state (renewal, changed domains, revocation) using the stored key. If this fails, the previous valid state is kept unchanged. |
| **Remove licence** | Deletes this root page's licence state (confirmation required). Content, findings, drafts and settings are left untouched. |

The key is never shown again and leaves the server only toward V-T.ONE.
Operation is a plain Contao form (POST + request token + page permissions),
with no custom JavaScript.

### What happens without a licence

| Area | Behaviour without a valid licence |
|---|---|
| "Accessibility" backend hub | A notice instead of the tools; only licensed root pages are selectable |
| Full scan / database analysis / ARIA ingest / preview | HTTP 403, no data change |
| Database linter | writes no findings for unlicensed root pages and does not change their existing findings |
| CLI commands, cron, monitoring | abort with a notice |
| Overlay, plain language, subtitle `<track>`, ARIA injection | no output, default Contao page |
| Frontend modules (statement, feedback channel, switch) | render nothing |
| Reports inbox | read-only — submitted reports are never hidden or deleted |

### Data transmission (transparency)

Licence communication goes **exclusively** to `https://www.v-t.one` (a fixed
address, not redirectable via configuration, TLS verification on, redirects
off). It is a prerequisite of the product and therefore **not** governed by
the "No external calls" switch, which controls AI/content egress only.

On activation or refresh, the entered licence key and the root page's domain
are transmitted, exclusively on a backend click. An anonymous usage signal
(product + domain) is sent at most once per request in which the bundle was
active for a licensed domain. Opening the licence section or the hub checks
the licence state once per logged-in backend session and root page. V-T.ONE
can also deliver an updated licence state server-side; inbound deliveries
are fully cryptographically verified and discarded without a valid
verification.

Ordinary logs only ever contain the operation, a generic result category,
the HTTP status, duration and licence version — never the transmitted data
packets themselves or their authentication material.

### Operations

- Licence state is stored per root page privately, outside the docroot,
  under `var/accessplus/` (in `.gitignore`) and cannot be copied between
  installations — it is bound to the exact host and is re-verified
  cryptographically on every check.
- Requires the PHP extension `sodium` for signature verification. If it is
  missing, the section reports this in plain text and activates nothing.
- Delivered updates are checked server-side against an internal replay
  memory. With multiple nodes (load balancing), `var/` must be shared
  between the nodes.

## B5. AI connection

All AI features run through a provider abstraction — **OpenAI** or
**OpenAI-compatible** (custom base URL, e.g. a self-hosted model):

- **Alt text:** a vision model (image as a data URI in the request).
- **Subtitles:** the Whisper endpoint (`/audio/transcriptions`, multipart,
  VTT).
- **Plain/easy language:** text completion, plain-text output, a length
  budget against overly long rewrites.

No call happens without the egress switch disabled. Keys are only sourced
from the encrypted store.

## B6. Data model (tables)

| Table | Content |
|---|---|
| `tl_accessplus_finding` | Findings (DB + frontend), fingerprint-deduplicated, occurrences, sampleUrl |
| `tl_accessplus_run` | Full-analysis snapshots (score, timestamp) |
| `tl_accessplus_altsuggestion` | Alt-text suggestions (pending/approved/rejected) |
| `tl_accessplus_track` | Subtitle drafts (VTT, status, provider/model) |
| `tl_accessplus_simplification` | Plain/easy-language drafts (register, lock) |
| `tl_accessplus_feedback` | Feedback-channel submissions (new/in progress/done) |
| `tl_accessplus_audit` | Append-only log of every automatic change (undo) |

State outside the database: `var/accessplus/config.json`,
`var/accessplus/secret.key` (0600), `var/accessplus/secrets.json`.

## B7. Security

- Keys encrypted at rest (defuse), encryption key stored separately (0600),
  never in the database/logs/frontend.
- Egress kill switch enforced at multiple layers (client + tester), ON by
  default.
- Write actions only via POST + request token, backend firewall; SSRF guards
  on base URLs; data minimisation + masked logging.
- Frontend forms: honeypot + token (via insert tag, cache-safe) + server-side
  validation, escaping.
- AI golden rule: generating **never** writes live — only approval writes,
  and metadata is never overwritten.
- Licence state is fully cryptographically re-verified on every check, not
  only once at save time.

## B8. Monitoring

Three triggers for repeat checks, without requiring a dedicated system cron:

- **CLI:** `accessplus:monitor`
- **Contao cron:** daily (`contao.cronjob`, interval=daily) — runs through
  Contao's own "poor-man's cron" (triggered by frontend requests), so it also
  works on hosting without real system cron access.
- **On save:** a hook on `tl_content` / `tl_page` / `tl_form_field`
  (optional, throttled via a configurable interval; never aborts the save
  itself).

The frontend axe scan does **not** run automatically (only possible in a
browser).

## B9. CLI commands

```bash
accessplus:scan                         # database linter
accessplus:pdf:scan                     # PDF check
accessplus:alt:generate                 # alt-text suggestions
accessplus:subtitles:generate --lang de --limit 5
accessplus:simplify --register einfach --limit 200
accessplus:monitor                      # re-scan + timestamp
```

Use the CLI for large video/audio batches (avoids web timeouts).

## B10. Deployment notes (important on Plesk!)

- **On Plesk, `public/bundles/` is a COPY, not a symlink.** A manual file
  upload updates `vendor/` + `composer.json` (the Manager shows the new
  version), **but not** `public/bundles/vtinnovationsaccessplus/*.js/css`.
  Old JS is then served.
- **Fix:** manually copy the contents of `Resources/public/` to
  `public/bundles/vtinnovationsaccessplus/` **and clear the cache**.
- **Diagnosis:** open the direct URL
  `/bundles/vtinnovationsaccessplus/accessplus-fullscan.js` and compare the
  file size/timestamp against `Resources/public/accessplus-fullscan.js`. The
  `?v=` suffix only busts the browser cache, not the stale server copy.

## B11. Troubleshooting

| Symptom | Cause / fix |
|---|---|
| Full scan "HTTP 400" | Stale `accessplus-fullscan.js` (server copy). Re-copy assets + clear cache (see B10). |
| Scan loads pages over `http://` | Enable "Force HTTPS" (useSSL) on the root page — Contao then emits `https` URLs. |
| Frontend score "no analysis" | Run at least one full scan **or** "database analysis only"; check the licence for the domain. |
| Hub shows "no valid licence" | Store the licence on the **root page** (B4). Most common cause: the root page has no domain, or a different one than the licensed domain (`www.` counts separately). |
| "The stored licence needs a one-time update" | An older licence state, not yet in the current format — click **Refresh licence** once. |
| "Crypto extension missing" | Enable the `sodium` extension with the host; without it, no signature verification and therefore no activation is possible. |
| "Done" resets on every scan | Fixed: frontend findings are only marked fixed with **full** page coverage (X-Frame-Options / CSP must allow `SAMEORIGIN`). |
| False contrast findings | The iframe now scrolls through (so reveal animations fire); remove old false positives via "Reset frontend findings". |
| Subtitles invisible on the frontend | The bundle injects `<track>` automatically on `ce_player`; the VTT file must sit next to the source as `file.<lang>.vtt` (approval does this). |
| AI call does not happen | The "No external calls" egress switch is still ON, or the API key is missing/invalid. |

## B12. Known limitations (honest)

- Third-party sliders with their **own** image table (not Contao
  `multiSRC`) are not captured by the database alt-text check — only the
  frontend axe scan catches them.
- Cross-origin pages (a different domain) cannot be scanned in the iframe.
- Backend subtitle transcription can hit the PHP time limit on large files →
  use the CLI.
- Custom/RSCE detail pages are not auto-discoverable; only their listing page
  is scanned (template problems still surface through that).

---

## Appendix — Backend tabs at a glance

`Dashboard` (score + full scan incl. frontend scan) · `Report` (findings) ·
`PDF` · `AI alt text` · `ARIA names` · `AI subtitles` · `Plain language` ·
`Overlay` · `Statement` · `History` (audit/undo).

Separate backend module: `Reports` (feedback inbox).

The **licence** does not live in the hub but on the respective root page
(*Site structure → edit root page → AccessPlus Licence management*).
