# vtinnovations/accessplus — Accessibility for Contao

*[Deutsche Version](README.md)*

Native accessibility **auditing**, **repair** and **monitoring** directly inside
the Contao backend. Not a bolted-on overlay patch, but anchored in the CMS:
**audit → generate → safeguard → monitor**, plus the legally required
documents. Data stays with the customer; runs on **Contao 4.13 through 5.7+**
with **PHP 8.1+**, including Plesk / shared hosting without SSH access
(Contao's own cron system, no system cron required).

> **In short:** A backend module "Accessibility" finds the a11y problems on
> your site (database **and** the real frontend via axe-core), suggests
> repairs (AI alt text, ARIA names, AI subtitles, plain/easy language among
> others), delivers the mandatory accessibility statement and feedback
> channel, and continuously monitors the state with an honest score.

> **Important note:** This bundle is a tool to improve accessibility. It makes
> **no** statement about legal or regulatory (e.g. EAA/BFSG/BITV) compliance
> and does not replace expert/human review. AI content is always a **draft**
> and never goes live automatically. The comfort overlay is an honest addition,
> **not** a substitute for genuine accessibility.

---

## Status

Production-grade, native Contao implementation (no prototype, no placeholder).
Current version: see `composer.json`.

## Feature overview

A backend hub "**Accessibility**" with tabs bundles everything:

| Area | What it does |
|---|---|
| **Dashboard** | Score ring (0–100, severity-weighted), stat cards, 3-column layout (Done / One-click / Manual), history & trend ▲▼, **one** "Start full scan" (database **+** frontend, with progress bar, interruptible & resumable) |
| **Report** | Every finding with a plain-language explanation, a concrete fix hint, "Show on page" (live highlight) and "Fix now" (deep link into the matching Contao edit screen) |
| **Database linter** | 5 rule-based checks without rendering: missing alt text, heading hierarchy, vague link text, missing form labels, page language |
| **Frontend scan** | axe-core 4.10 (bundled locally, no remote loader) checks the real, published pages in a same-origin iframe — catches contrast, landmark and DOM issues the database never sees. Legal scope only (WCAG 2.x A + AA). Triggered from the Dashboard's full scan, not a separate tab |
| **AI alt text** | Vision AI generates alt-text **suggestions** per language, only for images that are actually **used**; the editor edits & applies; only writes into empty meta slots (never overwrites), fully reversible |
| **ARIA names** | For elements the frontend scan reports as unnamed (links, buttons, frames, form fields …): the editor reviews a prefilled name suggestion and approves it; applied at runtime as `aria-label`, never over an existing name |
| **AI subtitles** | Whisper transcription of audio/video → **VTT draft** → approval writes `.vtt` next to the source and wires up `<track>` in the frontend automatically |
| **Plain / easy language** | AI **draft** per register (plain language ~B1, easy language ~A1); in-place frontend switching via toggle/overlay; strict review, locking against regeneration, never auto-live |
| **Overlay** | Optional comfort widget with 30 individually switchable features across 4 groups: **profiles** (epilepsy/low-vision/ADHD presets), **reading** (font size, readable font/OpenDyslexic, line/letter spacing, reading guide, bionic reading, link navigation …), **orientation** (contrast modes, grayscale, text-to-speech, stop animations, mute sound, big cursor, focus/hover highlight, text alignment …), **colors** (text, title, link and background color) |
| **Statement & feedback channel** | Editor for the accessibility **statement** (mandatory document) + a frontend feedback form with a backend inbox and email notification |
| **PDF check** | Detects missing title / missing language / missing tags in PDFs (detection + guidance, without modifying the file) |
| **Monitoring** | Repeats checks automatically: CLI command, Contao cron (daily, poor-man's cron — no system cron required) and an optional on-save hook — throttled, with score trend |
| **Audit & undo** | Every automatic change is logged (who/when/before/after) and can be undone |
| **Licence** | Pro licence **per root page**, bound to its **exact** hostname; managed in the root page settings |

---

## Feature status

The bundle has **no** free tier: without an activated Pro licence for the
respective root page, Contao is unchanged and all features below are off.

| Feature | Status |
|---|---|
| Database linter, frontend scan (axe-core), dashboard/score | Pro only |
| AI alt text, ARIA names, AI subtitles, plain/easy language | Pro only, additionally **conditional** on a configured AI access |
| Overlay (comfort widget) | Pro only |
| Statement & feedback channel (frontend modules) | Pro only |
| PDF check | Pro only, **conditional** on `smalot/pdfparser` (mandatory Composer dependency, always installed) |
| Monitoring (CLI/cron/save hook) | Pro only |
| Audit & undo | Pro only |

---

## Installation

### Composer / Contao Manager

```bash
composer require vtinnovations/accessplus
```

Then run the migrations, either in the Contao Manager or via CLI:

```bash
vendor/bin/contao-console contao:migrate
```

This creates the `tl_accessplus_*` tables and installs the frontend assets
(`vendor/bin/contao-console assets:install`, done automatically by the
Manager).

> **Migrate without `--with-deletes`** if the Manager shows DROP statements
> from other packages that you do not want to execute — this bundle's CREATE
> statements run fine without it.

### Requirements

- Contao **4.13** or **5.3–5.7+**
- PHP **8.1+**, PHP extension **sodium** for licence verification (present on
  virtually every host running PHP 8.1+)
- For AI features: an **OpenAI** or **OpenAI-compatible** API access (custom
  base URL supported). All non-AI features work without a key/authorisation.

---

## Getting started

1. **Site structure → edit root page → "AccessPlus Licence management"**:
   enter the licence key and click **"Check and activate licence"**. Without
   a valid licence for that root page the bundle stays inactive and Contao
   behaves exactly as if it were not installed (see DOKUMENTATION.en.md B4).
2. **Backend → Accessibility → Settings**: enter the API key for AI usage and
   disable the **"No external calls"** switch (default: ON = no AI/content
   egress).
3. Run the **connection test** (a probe call that respects the egress switch).
4. **Dashboard → "Start full scan"**: the database analysis and frontend scan
   run one after another, then the score appears.
5. Work through the findings in the **Report** — apply one-click fixes, fix
   manual ones via "Fix now" in the Contao edit screen.
6. Fill in the **statement and feedback channel** and embed the two frontend
   modules (statement + feedback) on a page.

Detailed steps for every tab: [DOKUMENTATION.en.md](DOKUMENTATION.en.md), Part A.

---

## Permissions & access control

All backend tabs sit behind the regular Contao backend firewall and the
Contao user/group permissions for the "Accessibility" module. Write actions
run exclusively via POST with a Contao request token; there is no mutating
GET route. Licence management additionally requires root-page edit
permissions in the site structure.

---

## Security & privacy

- **No plaintext keys.** API keys are encrypted at rest (defuse); the
  encryption key lives in a separate file (`0600`), never in the
  database/VCS/logs/frontend.
- **Egress kill switch "No external calls" — ON by default.** While active, no
  AI/content data packet leaves the server. Enforced at multiple layers.
- **No mutating GET route.** All write actions go through POST + request
  token, behind the backend firewall.
- **Data minimisation & masked logging** for external calls; SSRF guards on
  base URLs.
- **AI content never auto-live** — the editor reviews and approves; metadata
  is never overwritten; everything is audited and reversible.

Details on the security model: [DOKUMENTATION.en.md](DOKUMENTATION.en.md), section B7.

---

## Operational safety

- **The egress switch is independent of licence verification.** "No external
  calls" governs AI/content calls only; the licence verification required for
  operation is unaffected (see the Licence section). An optional monitoring
  hook on content save never aborts the save itself.
- **AI outages block nothing existing.** A failed or disabled AI call only
  prevents new suggestions; already-approved content is left untouched.

## Runtime directories

State outside the database lives under `var/accessplus/` (outside the public
docroot, excluded from version control via `.gitignore`): licence state per
root page, the encryption key file (`0600`), and encrypted credentials.
Details: DOKUMENTATION.en.md B6.

## External communication

- **Licence verification:** exclusively `https://www.v-t.one`, independent of
  the egress switch (see the Licence section and DOKUMENTATION.en.md B4).
- **AI features:** to the OpenAI or OpenAI-compatible endpoint configured in
  the Settings tab, only while the egress switch is disabled.
- No other external calls, no remote tracking, axe-core is bundled locally.

---

## Technical framework

- Package `vtinnovations/accessplus`, namespace `VTInnovations\AccessPlus\`,
  DB prefix `tl_accessplus_`, state under `var/accessplus/`.
- Version branching exclusively via `Compatibility\Compat` (no scattered
  `version_compare`).
- AI providers via an abstraction: **OpenAI** + **OpenAI-compatible**.
- axe-core bundled locally (MPL-2.0), no external loader/tracker.

### CLI commands

```bash
accessplus:scan                         # database linter
accessplus:pdf:scan                     # PDF check
accessplus:alt:generate                 # AI alt-text suggestions
accessplus:subtitles:generate --lang de --limit 5
accessplus:simplify --register einfach --limit 200
accessplus:monitor                      # re-scan + timestamp
```

### Quality gates

```bash
composer install
vendor/bin/phpunit                # unit tests (mocked, no network)
```

Verified: `composer install` resolves cleanly under PHP 8.1 (needs the PHP
extensions `intl` and `gd` for `contao/core-bundle`/`contao/image`), and
`vendor/bin/phpunit` passes (93 tests, 244 assertions). Static analysis
(e.g. PHPStan) and a coding-standard fixer (e.g. ECS) are **not** declared
as dev dependencies and are not available via `vendor/bin/` without
additional setup of your own.

### Cache clearing

Clear the usual Contao/Symfony cache after configuration changes or
deployment:

```bash
vendor/bin/contao-console cache:clear
```

---

## Tests

The security-critical parts (licence exchange, cryptographic verification,
state storage) are covered by PHPUnit (`vendor/bin/phpunit`, mocked, no
network access) — verified passing: 93 tests, 244 assertions. Details:
`tests/`.

## Troubleshooting & known limitations

Detailed troubleshooting table and honest limitations (e.g. third-party
sliders without a Contao image table, cross-origin pages in the frontend
scan, PHP time limits on large subtitle batches): see
[DOKUMENTATION.en.md](DOKUMENTATION.en.md), sections B11 and B12.

---

## Licence

Code: **LGPL-3.0-or-later**.

Usage licence: **Pro — per root page and exact hostname**, obtained through
V-T.ONE. There is no trial, free or grace tier: without an activated,
cryptographically verified Pro licence, the features are off for the
respective root page. It is managed under *Site structure → edit root page →
AccessPlus Licence management*.

`www.example.com` and `example.com` are treated as **different** hosts; each
root page needs the domain it is actually configured with in Contao.

Licence verification contacts exclusively `https://www.v-t.one` — independent
of the "No external calls" switch, which governs AI/content egress. Details
on the data transmitted: DOKUMENTATION.en.md B4.

Copyright © V&T Innovations Team. Website: [www.v-t.one](https://www.v-t.one).

Full user and administrator guide: see [DOKUMENTATION.en.md](DOKUMENTATION.en.md)
([Deutsch](DOKUMENTATION.md)).
