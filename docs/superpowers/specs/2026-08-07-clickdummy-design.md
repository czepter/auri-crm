# Clickdummy — Design-Only Mockups

**Date:** 2026-08-07
**Status:** Approved. Revised after adversarial review — screen count cut from 37 to 12, shared data file removed, Tailwind mechanism corrected.
**Parent:** [Product Vision](2026-08-07-photography-client-management-vision-design.md)
**Scope:** 12 linked static HTML screens across the admin, portal, and gallery surfaces. Design-only — no build step, no backend, no state.

---

## 1. Purpose

Settle the layout and information-architecture questions the vision leaves open, before P1 implementation begins. The mockups are throwaway: nothing in `mockups/` is intended to survive into `apps/studio`. What survives is the decisions they settle.

### The selection rule

A mockup set has no compiler. Every consistency guarantee is manual, so its true cost scales with screen count **times** review rounds, not with screen count. Twelve screens reviewed three times is affordable; thirty-seven is not.

A screen earns its place only if it satisfies both tests:

1. **It has an open question.** Login forms, TOTP prompts, contact lists, calendars, settings pages, and confirmation screens are solved patterns. Mocking them produces no decision — it just draws a CRUD form in HTML that gets drawn again in Vue weeks later.
2. **Static HTML can express what it asks the reviewer to judge.** If the property under review is friction or scale, static screens cannot represent it, and a confident wrong answer is worse than no answer. See §5.

Roughly twenty candidate screens failed test 1 and were cut. Two failed test 2 and were kept with their scope explicitly narrowed.

### Non-goals

Responsive breakpoints beyond Tailwind defaults · admin mobile layouts (staff work at desks) · dark mode outside the gallery · accessibility audit · component API design · working behavior of any kind · every screen not listed in §3.

---

## 2. Medium and structure

Static HTML with the Tailwind browser build via CDN. No npm, no build, no server. `index.html` opens by double-click.

```
mockups/
├── index.html              screen map — 12 screens grouped by surface, each with its review question
├── _shared/
│   ├── layout.js           chrome: admin sidebar+topbar, portal header, gallery header, per-screen footer
│   └── tokens.css          shadcn token block, three themes
├── admin/                  5 screens
├── portal/                 2 screens
└── gallery/                5 screens
```

### Tailwind and tokens — verified behavior

The Play CDN is Tailwind **v4**, loaded as `@tailwindcss/browser@4`. It is themed through the CSS-first `@theme` directive inside `<style type="text/tailwindcss">`, not through a JS config object. shadcn's current token set is OKLCH values on `:root` mapped into Tailwind via `@theme inline`, which means **shadcn's token block pastes in verbatim**. The themes here are the real ones, not approximations of them.

`@tailwindcss/browser` installs a `MutationObserver` on `document.documentElement` with `childList: true, subtree: true` and rebuilds on added nodes. Injected chrome is therefore picked up normally, and `layout.js` can run on `DOMContentLoaded` with no ordering contortion. (An earlier draft of this spec claimed the opposite and designed a blocking-head-script mechanism around it. That claim was wrong.)

Chrome ships as template strings in `layout.js` loaded by `<script src>` rather than as partials loaded by `fetch`, because a local file's origin is opaque and `file://` blocks `fetch` under the same-origin policy. Script tags carry no such restriction.

### No shared data file

Sample data is hardcoded into each screen. A `_shared/data.js` was specified in the previous draft and removed: static HTML has no templating, and §5 forbids the JavaScript that would read it, so the file could never have been used. At twelve screens, hand-consistency is affordable — which is itself part of why the count is twelve.

### Theming

Three token blocks in `tokens.css`, switched by `data-theme` on `<html>` — the same mechanism `apps/studio` will use.

| Theme | Surface | Character |
|---|---|---|
| `admin` | staff | Dense. Tight radii, 13px base, compact table rows, muted neutrals. Reads as a tool. |
| `portal` | clients | Spacious. Generous whitespace, serif display face, studio accent, larger touch targets. |
| `gallery` | guests | Near-black. Chrome recedes; photographs carry the page. |

### Placeholder branding

**Atelier Nordlicht**, Hamburg — wedding and portrait. Desaturated terracotta accent, serif display face, Inter for UI. A warm neutral was chosen because a saturated brand color fights every wedding photograph placed behind it, and the portal and gallery are mostly photograph.

### Sample data

One consistent cast: the Meier–Kowalski wedding at Gut Basthorst on 14.06.2026, one portrait session, and several open leads at different pipeline stages. Clicked through in order the mockups tell one story rather than reading as twelve unrelated screenshots.

### Language

German throughout, UI copy and sample data both. German compound nouns run roughly 30% longer than their English equivalents and break column and button widths that looked fine in English — surfacing that now is cheaper than surfacing it during implementation.

### Photography

Wedding and portrait images hotlinked from the Unsplash CDN with size parameters, so mixed aspect ratios and lightbox framing are honest. Requires internet to view; no image files in the repo.

---

## 3. Screen inventory

### Admin — theme `admin` (5)

| # | Screen | Open question it settles |
|---|---|---|
| 1 | Übersicht — Dashboard | What does a photographer need on opening the app? Today's shoots, overdue installments, and the "Prüfung erforderlich" tray compete for the same space — which wins. |
| 2 | Anfragen — Pipeline-Board | Does a 5-column `Lead.stage` board stay legible at realistic density (11 leads), and what fits on a card without a hover state. |
| 3 | Anfrage anlegen | Manual lead entry, now P1's only capture path. Where the availability conflict against existing jobs appears, and how hard it interrupts. |
| 4 | Auftrag — Detail | The densest screen in the system: forward-only status pipeline, dates, venue, typed contacts, crew, and tab affordances for tasks, notes, payments, and gallery. Whether tabs are the right split at all. |
| 5 | Medien-Upload | Presigned multipart progress across ~1200 files with 3 failures. Whether a partially-failed 30 GB upload is legible or panic-inducing. |

### Portal — theme `portal` (2)

| # | Screen | Open question it settles |
|---|---|---|
| 6 | Startseite — Checkliste | Does the portal read as studio-branded rather than as an internal tool? Plus whether a derived task checklist feels like service or like homework. |
| 7 | Vertrag — unterschreiben | The trust moment. Typed full name plus explicit consent checkbox, alongside the rendered PDF. Whether a simple electronic signature looks credible enough that a client completes it without emailing to ask. |

### Gallery — theme `gallery` (5)

| # | Screen | Open question it settles |
|---|---|---|
| 8 | Galerie — Passwort | First impression for eighty guests, and whether uniform-failure copy (§5 of the vision) can be written without sounding broken. |
| 9 | Galerie — Raster | Section navigation and mixed portrait/landscape runs. **Scope-limited** — see §5. |
| 10 | Lightbox | Favorite and comment affordances without chrome overwhelming the photograph. |
| 11 | Download — PIN | The moment a guest discovers a *second* secret exists. Layout and copy only — **not** friction; see §5. |
| 12 | Download — E-Mail | DSGVO purpose statement and retention period at the point of collection, without the form reading as a data grab. |

**Cut and why:** login, TOTP, link-sent confirmation, contacts list, contact detail, calendar, settings, audit log, packages, quote builder, job sub-tabs as separate screens, portal payments, questionnaire, messages, download-sent, favorites view. All solved patterns with no open design question — they fail test 1 of §1.

---

## 4. Interaction rules

No state and no JavaScript behavior beyond chrome injection and `<a>` navigation.

**Screens depict one moment**, in the most informative state rather than empty: the board carries 11 leads across 5 columns, the upload sits mid-transfer with 3 failures, the grid runs a mixed portrait and landscape sequence.

**Forms are inert.** Inputs render pre-filled with plausible values; buttons link forward. No validation and no error states, except the two the vision makes a security claim about — wrong gallery password (uniform failure) and rate-limited PIN entry (lockout). With no state to toggle, each renders as a second labeled variant block further down the same page, so the copy can be reviewed side by side with the resting state.

**Every screen carries a footer strip** with its number, its surface, and links back to the screen map plus previous and next in flow order.

**`index.html` is the spine** — twelve labeled entries grouped by surface, each carrying the open question from §3 so review comments land on the decision rather than on the styling.

---

## 5. What these screens cannot answer

Two of the twelve review properties that static HTML structurally cannot represent. Both screens are kept, with their claims narrowed rather than quietly overstated.

**Screen 11 — the download gate flow is not friction-testable here.** Friction is time and effort, and clicking `<a href>`s erases both. The real sequence is: find the password in a WhatsApp thread, type it, press download, discover a second secret exists, ask the couple for the PIN, wait, type an email, leave the site, wait for mail, return. A static click-through takes three seconds and will report "fine" for something that may be genuinely hostile. These screens judge layout and copy only. Whether three gates are one too many is a question for a real user on a real phone, and it stays open after this exercise.

**Screen 9 — the grid is not scale-testable here.** The real risk is whether 1500 images read well. A static page holding 1500 hotlinked images is unopenable and would be rate-limited long before it loaded. The screen shows roughly 40 and judges section navigation, aspect-ratio mixing, and placeholder behavior. Scroll performance, virtualization, and pagination remain P3 questions.

Naming these is the point: a mockup that quietly fails to test its property is worse than one that never claimed to.

---

## 6. Build order

`_shared/` first — tokens and chrome. Then screens 1, 2, and 4 as the pattern-setter, since they establish every table, card, badge, and form convention the rest inherit.

**Those three are reviewed before the remaining nine are produced.** At twelve screens a single mid-course correction is cheap; the previous 37-screen plan had one review gate covering 33 screens, where convention errors surface at screen 30.

Then the remaining admin screens, then portal, then gallery. Gallery last: it depends least on conventions established elsewhere and most on the photographs.

---

## 7. Vision changes already applied

Recorded here for traceability; both are live in the parent document as of this spec.

**Lead capture is manual in P1.** No public inquiry form. §9 of the vision previously justified deploying the `api` surface in P1 specifically because a public inquiry endpoint must be internet-facing while admin is network-restricted. With no public form that consumer disappears, so **P1 deploys the admin container alone** and the `api` surface moves to P3 alongside the Astro gallery. One fewer container to operate, and one fewer internet-facing attack surface during the phase with the least hardening.

**The public form is deferred, not abandoned.** The vision now records why it deserves its own phase: it would be the only surface in the system that is anonymous *and* writes to the database — the only place needing honeypot, timing check, and per-IP rate limiting — and the only genuine cross-origin case, posting from the marketing site to `api.studio.tld`.

---

## 8. Deliverable

`mockups/` committed to this repository. Opened by double-clicking `index.html`. No server, no npm, no build step.
