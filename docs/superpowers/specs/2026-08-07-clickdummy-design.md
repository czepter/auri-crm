# Clickdummy — Design-Only Mockups

**Date:** 2026-08-07
**Status:** Approved.
**Parent:** [Product Vision](2026-08-07-photography-client-management-vision-design.md)
**Scope:** 37 linked static HTML screens covering the admin, portal, and gallery surfaces. Design-only — no build step, no backend, no state.

---

## 1. Purpose

Validate layout, flow, and information architecture across all three surfaces before P1 implementation begins. The mockups are a throwaway artifact: nothing in `mockups/` is intended to survive into `apps/studio`. What survives is the design decisions they settle and the vision edits they provoke.

They exist to answer questions that a written spec cannot: whether the pipeline board is legible at a realistic lead count, whether the portal reads as studio-branded rather than as an internal tool, whether German copy fits the columns and buttons, and whether the gallery's three-gate download flow feels defensible or obstructive to a guest.

### Non-goals

Responsive breakpoints beyond Tailwind defaults · admin mobile layouts (staff work at desks) · dark mode outside the gallery · accessibility audit · component API design · P4 automation rule builder · any working behavior.

---

## 2. Medium and structure

Static HTML with Tailwind via CDN. No npm, no build, no server. `index.html` opens by double-click.

```
mockups/
├── index.html              screen map — all 37 screens grouped by surface, each with a review note
├── _shared/
│   ├── layout.js           chrome: admin sidebar+topbar, portal header, gallery header, screenmap footer
│   ├── tokens.css          shadcn CSS variables — three themes
│   └── data.js             the shared sample cast
├── admin/                  21 screens
├── portal/                 9 screens
└── gallery/                7 screens
```

### Shared chrome

Sidebar, topbars, and the per-screen footer strip live once in `_shared/layout.js` as template strings, injected on load. Review churn is the dominant cost of a clickdummy, and full duplication taxes exactly that — renaming one nav label must not be a 30-file edit.

Two constraints drive the mechanism:

1. **`<script src>`, not `fetch`.** A local file's origin is opaque, so `file://` blocks XHR and `fetch` under the same-origin policy. Script tags were never same-origin restricted, so chrome ships as template strings in a script rather than as fetched partials.
2. **Injection runs before Tailwind.** The Tailwind CDN build compiles classes in-browser by scanning the DOM. Chrome injected on `DOMContentLoaded` would arrive after that scan and get no CSS. Injection therefore happens in a blocking script in `<head>`.

### Theming

Three CSS-variable blocks in `tokens.css`, switched by `data-theme` on `<html>` — the same mechanism `apps/studio` will use, so the token names transfer even though the files do not.

| Theme | Surface | Character |
|---|---|---|
| `admin` | staff | Dense. Tight radii, 13px base, compact table rows, muted neutrals. Reads as a tool. |
| `portal` | clients | Spacious. Generous whitespace, serif display face, studio accent, larger touch targets. |
| `gallery` | guests | Near-black. Chrome recedes; photos carry the page. |

### Placeholder branding

**Atelier Nordlicht**, Hamburg — wedding and portrait. Accent is a desaturated terracotta; serif display face, Inter for UI. A warm neutral was chosen because a saturated brand color fights every wedding photograph placed behind it, and the portal and gallery are mostly photograph.

### Sample data

One consistent cast across all 37 screens: the Meier–Kowalski wedding at Gut Basthorst on 14.06.2026, one portrait session, and three open leads at different pipeline stages. Clicked through in order the mockups then tell one story rather than reading as 37 unrelated screenshots.

### Language

German throughout, UI copy and sample data both. German compound nouns run roughly 30% longer than their English equivalents and break column and button widths that looked fine in English — surfacing that in mockups is cheaper than surfacing it during implementation.

### Photography

Real wedding and portrait images hotlinked from the Unsplash CDN with size parameters, so grids, mixed aspect ratios, and lightbox behavior are honest. Requires internet to view; no image files in the repo.

---

## 3. Screen inventory

### Admin — `APP_SURFACE=admin`, theme `admin` (21)

| # | Screen | Exercises |
|---|---|---|
| 1 | Anmeldung — Passwort | §4 staff auth |
| 2 | Zwei-Faktor — TOTP | §4 mandatory MFA |
| 3 | Übersicht — Dashboard | Today's shoots, needs-attention tray, overdue installments |
| 4 | Anfragen — Pipeline-Board | §7 `Lead.stage` kanban, 5 columns |
| 5 | Anfrage-Detail | Source, requested date, availability conflict flag, convert action |
| 5a | Anfrage anlegen | Manual entry with inline availability check against existing jobs |
| 6 | Aufträge — Liste | §7 `Job.status` filters |
| 7 | Auftrag — Übersicht | Forward-only status pipeline, dates, venue |
| 8 | Auftrag — Beteiligte & Team | §3.1 `JobContact` roles, `CrewAssignment` |
| 9 | Auftrag — Aufgaben | §3.3 internal vs. client audience split |
| 10 | Auftrag — Notizen | §3.3 `InternalNote`, visibly staff-only |
| 11 | Auftrag — Zahlungen | §7 installments, Stripe state |
| 12 | Auftrag — Galerie | §3.4 versions, publish state |
| 13 | Kontakte — Liste | §3.1 role-agnostic `Contact` |
| 14 | Kontakt-Detail | Cross-job history |
| 15 | Kalender | Shoots plus ICS export |
| 16 | Medien-Upload | §6 presigned multipart progress and "Prüfung erforderlich" tray |
| 17 | Pakete & Angebote | §3.2 catalog |
| 18 | Angebot erstellen | Line items, price snapshot, send |
| 19 | Einstellungen | Studio details, templates, and the seeded automation rules as a read-only list — §7 defers the rule builder |
| 20 | Protokoll — Audit-Log | §4 append-only |

### Portal — `APP_SURFACE=portal`, theme `portal` (9)

| # | Screen | Exercises |
|---|---|---|
| 21 | Anmeldung — E-Mail-Formular | §4 self-service magic link |
| 22 | Link gesendet | Response identical for known and unknown email |
| 23 | Startseite — Checkliste | §3.3 derived client task list |
| 24 | Vertrag — Ansicht | §7 rendered PDF in-page |
| 25 | Vertrag — unterschreiben | Typed full name plus explicit consent checkbox |
| 26 | Vertrag — bestätigt | Countersigned PDF, SHA-256 hash shown |
| 27 | Zahlungen | Installments, Stripe hosted-page handoff |
| 28 | Fragebogen | §3.3 `FormInstance`, timeline questions |
| 29 | Nachrichten | §3.3 `Thread`, unread state |

### Gallery — Astro, theme `gallery` (7)

| # | Screen | Exercises |
|---|---|---|
| 30 | Galerie — Passwort | §5 view gate, uniform failure copy |
| 31 | Galerie — Raster | Sections, mixed aspect ratios, blurhash placeholders |
| 32 | Lightbox | Favorite and comment |
| 33 | Download — PIN | §5 download gate, separate from the view gate |
| 34 | Download — E-Mail | Purpose statement and retention period (DSGVO) |
| 35 | Download — versendet | Split ZIP parts listed |
| 36 | Favoriten | Selection view, Lightroom export hint |

The unglamorous screens — audit log, needs-attention tray, ZIP parts — are deliberately included. They are where the vision's claims either hold up visually or fail to.

---

## 4. Interaction rules

No state and no JavaScript behavior beyond chrome injection and `<a>` navigation. These rules keep that constraint from producing misleading screens.

**Screens depict one moment.** Everything stateful renders in its most informative state rather than empty: the pipeline board carries 11 leads across 5 columns, the upload screen sits mid-transfer with 3 failures in the tray, the gallery grid runs a mixed portrait and landscape sequence. Empty states get their own screens only where the vision makes a claim about them — a fully completed portal checklist qualifies; an empty needs-attention tray does not.

**Multi-step flows become multiple linked screens.** Password → PIN → email → sent is four files, each linking forward. The link is the fake; each screen is honest.

**Forms are inert.** Inputs render pre-filled with plausible values and buttons link forward. No validation and no error states, except where the vision makes a security claim about the response — wrong gallery password (§5, uniform failure) and rate-limited PIN entry (§5, lockout) each get a screen.

**Every screen carries a footer strip** with its number, its surface, and links back to the screen map plus previous and next in flow order. Reviewing 37 files without that is where clickdummies die.

**`index.html` is the spine.** All 37 screens as labeled thumbnails grouped by surface, each with a one-line note on what it is asking the reviewer to judge.

---

## 5. Build order

`_shared/` first — tokens, chrome, the sample cast. Then one vertical slice as pattern-setter: admin login → dashboard → pipeline → job detail.

**That slice is reviewed before the remaining 33 screens are produced.** Every later screen inherits its table, card, and form conventions, and correcting those across 37 files afterwards is the failure mode this ordering exists to avoid.

Then the admin bulk, then portal, then gallery. Gallery goes last because it depends least on conventions established elsewhere and most on the photographs.

---

## 6. Vision edits provoked

Recorded here rather than acted on. Each is applied to the vision document only once the mockups confirm it.

**Public inquiry form dropped from initial scope.** Lead creation is manual (staff-entered) at the start. §9 justified deploying the `api` surface in P1 specifically because the public inquiry endpoint must be internet-facing while admin is network-restricted. With no public form, that consumer disappears: P1 becomes the admin container alone, and the `api` surface slips to P3 alongside the Astro gallery. Simpler, and one fewer container to operate.

A consequence worth carrying forward whenever the public form does return: it would be the only surface in the system that is anonymous *and* writes to the database. Every other write path sits behind a gate. It is therefore the one place needing spam defense — honeypot, timing check, per-IP rate limit — and the only genuine cross-origin case, since it would post from `www.studio.tld` to `api.studio.tld`.

---

## 7. Deliverable

`mockups/` committed to this repository. Opened by double-clicking `index.html`. No server, no npm, no build step.
