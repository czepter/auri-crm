# UX audit, November 2026

Rating log for the review loop against a local test instance (DemoCrmSeeder,
v0.15.0 plus the fixes in this PR). Method and scale: three reviewers (Opus,
Gemini 4 Argon, Gemini 3.8 Flash) rate screenshots blind on seven criteria;
a screen scores the average, capped at its weakest criterion plus 2.
Screenshots stay outside the repo (`.review/`).

## Round 0: baseline

Score that stands per screen and width, with each reviewer's average in
brackets (Opus / Argon / Flash). Criteria with a spread of 2 or more are
provisionally set to the median; the second pass is still open, so the
numbers are provisional. 114 of 196 criteria need it. Flash scores 1 to 2
points above the other two on most screens.

| Screen | 1440 | 1920 | 390 |
|---|---|---|---|
| Übersicht | 5.1 (4.4/5.1/6.6) | 5.1 (4.7/5.3/6.1) | 4.6 (4.1/4.7/5.6) |
| Anfragen | 5.1 (5.3/5.0/6.4) | 5.0 (5.1/4.9/6.1) | 4.4 (4.6/4.1/5.7) |
| Anfrage/Projekt | 5.0 (4.4/5.0/6.7) | 5.0 (4.6/5.0/6.6) | 3.9 (3.7/3.9/5.6) |
| Angebot (Editor) | 6.1 (6.0/6.0/7.3) | 5.9 (5.9/5.9/7.0) | 5.7 (5.6/5.6/6.4) |
| Kalender | 5.7 (5.3/5.4/6.6) | 5.6 (5.4/5.3/6.4) | 4.4 (4.7/4.3/5.3) |
| Auswertungen | 4 (3.6/4.9/6.1) | 4 (3.9/4.9/6.1) | 4 (3.3/4.6/5.4) |
| Portal Start | 6.1 (5.0/5.9/6.7) | 5.7 (4.7/5.7/6.3) | 5.0 (4.4/4.9/5.1) |
| Portal Angebot | 7.0 (6.4/7.3/7.6) | 5 (6.1/7.0/7.3) | 7.1 (6.7/7.0/8.0) |
| Galerie | 6.3 (5.4/6.4/7.7) | 6.3 (5.4/6.4/7.6) | 5.9 (5.3/5.9/7.6) |
| Admin am Handy | – | – | 3.9 (3.9/3.4/4.9) |

Portal Angebot at 1920 is capped by Layout (Opus 3, Argon 3, Flash 4).

### Findings from the handover

| # | Finding | Status |
|---|---|---|
| 1 | Draft quote offers "Angebot angenommen" | Confirmed in code, fixed. Not visible in the screenshots: the lead sits at "Kontaktiert". |
| 2 | Pipeline total and "Wert" | Confirmed, fixed. Pipeline summed closed and booked cases; "Wert" read only sent quotes (9 of 10 cards show "–", one has a 3.530 € draft). |
| 3 | Wide screens | Confirmed by all three: board, dashboard, portal quote and calendar leave more than a third empty at 1920. |
| 4 | Dashboard history | Repetition confirmed (four identical "Shooting-Status: Gebucht" rows). Width not rated. |
| 5 | Date column in the detail rail | Confirmed ("Do., 05.11.2026" wraps). |
| 6 | Empty appointment week | Confirmed, 6 of 7 day cells are "+". |
| 7 | "Neu" tiles | Not raised by any reviewer. Re-check. |
| 8 | "2× · 215 €" | Confirmed as "2× 180 €" on the inquiry card; the editor shows no quantity or unit price. |
| 9 | Contacts | "Keine Kontaktdaten" pale confirmed by two. Phone grouping not raised. |
| 10 | Duplicated stage information | Partly: "Angebot draußen" and "Angenommen" in the list differ from the board's "Angebot" and "Angebot angenommen". Chip "Liegt" not raised. |
| 11 | "offen" lowercase | Confirmed as "überfällig" lowercase beside capitalised KPIs. |
| 12 | One card, one type size | Confirmed: competing black buttons and KPI cards before the day's work. |

### New findings (two or more reviewers)

- Quote card on the inquiry shows the net amount as "Gesamt" (3.530 €); the editor's Gesamt is 4.200,70 €.
- Quote editor mixes three date formats ("2026-11-19", "19. Nov. 2026", "19.11.2026"); board cards use "05.09.26".
- Missing spaces: "läuftam", "Tagenerfolglo", "09:00Meier", "AufträgeUmsatz".
- Auswertungen funnel shows 0 after "Neu" while the board holds 3, 2 and 2 and the KPI says 1 gebucht. Cause not yet traced (may be the seed history).
- Portal project says the quote is still to come while a sent quote exists.
- Portal and guest gallery differ in hero, grid, button colour and label; guest form switches to "du".
- 390 px: top navigation of ten icons without labels, month grid without titles, inquiry list above the detail, board clipped, overlapping text in project "Termine", 10 px overflow from task dates.
- Help panel with explanatory text in the right column on most admin screens.

### Open

- Disputes (second pass) and single-reviewer findings.
- Refuted: the portal bottom bar over content at 390 px. It is `position: fixed` at the viewport bottom and `main` pads 112 to 160 px; the full-page capture drew it mid-page. All three reviewers scored Layout for Portal Start and Portal Angebot at 390 on it, so those scores are too low.
- The "Anfragen" list shots showed the board: `/anfragen` follows the stored view preference, the list is `/anfragen?ansicht=liste`. The list is re-captured and not yet rated.
- Seed artefacts excluded: placeholder Latin text, "Test User", the impossible shoot dates.

### Click benchmark (`docs/purpose.md`)

Every click on a link, button, option or field counts; typing is an input. Start on the dashboard at 1440 px.

| Task | Doc | Measured |
|---|---|---|
| Anfrage erfassen | 6 / 3 | 6 / 3 |
| Angebot versenden | 7 / 1 | 7 / 1, send blocked: "Widerrufsbelehrung fehlt" |
| Buchen | 4 / 0 | 4 / 0 |
| Vertrag versenden | 2 / 0 | not measurable: no contract template in the data |
| Galerie anlegen | 3 / 1 | 4 / 1 |

Galerie anlegen is one click over the document before any change in this loop: the dashboard tile does not prefill the project, so the project row is a click of its own.

## Rounds 1 to 8

Three-model blind council (Opus, Gemini 4 Argon, Gemini 3.8 Flash); the lowest score stands when the three are within 1, the median otherwise. Scores are out of 10 at 1440 / 1920 / 390. "Released" is v0.15.0 on the same data. Branch: `ux/r12-pg-final`.

### Admin

| Screen | Released | Now |
|---|---|---|
| Übersicht | 5.9 / 6.0 / 5.0 | 8.4 / 8.3 / 8.1 |
| Anfragen | 7.4 / 7.3 / 5.0 | 8.1 / 8.1 / 8.1 |
| Anfrage-Detail | 6.0 / 6.0 / 4.0 | 8.3 / 8.3 / 8.3 |
| Projekt-Detail | 5.7 / 6.0 / 4.1 | 8.1 / 8.1 / 8.3 |
| Kalender | 7.3 / 7.1 / 4.6 | 8.6 / 8.7 / 8.7 |
| Auswertungen | 5.0 / 5.0 / 4.3 | 8.4 / 8.4 / 8.4 |
| Angebot-Editor | 7.3 / 7.1 / 5.0 | 8.1 / 8.1 / 8.6 |
| Admin am Handy (5 viewport shots, 390) | 3.9 to 5.0 | 8.0 to 8.7 |

### Portal and gallery

| Screen | Released | Now |
|---|---|---|
| Portal Projekt | 4.0 / 4.0 / 4.0 | 7.6 / 7.6 / 7.6 |
| Portal Angebot | 6.0 / 5.0 / 7.0 | 8.6 / 8.4 / 8.6 |
| Portal-Galerie | 5.6 / 5.6 / 5.1 | 7.3 / 7.3 / 6.9 |
| Gast-Galerie | 6.0 / 6.0 / 5.7 | 7.9 / 7.9 / 7.6 |
| Admin-Galerie | 5.4 / 5.3 / 5.3 | 7.7 / 7.7 / 7.4 |

### Open

- Portal Projekt: rail shows the net amount where the quote page shows gross (seeded sent quote has no frozen VAT).
- Galleries: hero differs between portal and guest, hearts only on hover at desktop, low contrast on "Neue Liste" and "Anmelden", phone tiles leave a gap on the right.
- Admin: 1920 px blank bands on sparse data, dashboard and calendar help panels still narrate.
- Not re-rated after the last round: nothing; every row above is from the latest capture of its branch state.
- Issues to file: one gallery design for portal and guests; date and venue in the guest gallery header (API); phone nav gaps (Galerien in admin, Abmelden in portal); gallery strings outside the Anrede switch; quote number absent in the seed.
