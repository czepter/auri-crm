/* ------------------------------------------------------------------
   Shared chrome for the clickdummy.

   Loaded as <script src>, not fetch — a local file's origin is opaque
   and file:// blocks fetch under the same-origin policy. Script tags
   carry no such restriction.

   Screens declare what they are on <body>:
     data-nav="anfragen"  which sidebar item is active
     data-screen="2"      screen number, drives the footer strip
   ------------------------------------------------------------------ */

const ICON = {
  uebersicht: `<path d="M3 10.5 12 3l9 7.5"/><path d="M5 9.5V21h14V9.5"/>`,
  anfragen:   `<path d="M3 12h5l2 3h4l2-3h5"/><path d="M5.5 5h13l2.5 7v7H3v-7z"/>`,
  auftraege:  `<path d="M4 8h16v12H4z"/><path d="M9 8V5h6v3"/><path d="M4 13h16"/>`,
  kontakte:   `<circle cx="9" cy="8" r="3.2"/><path d="M3 20c0-3.3 2.7-5.5 6-5.5s6 2.2 6 5.5"/><path d="M16.5 5.5a3.2 3.2 0 010 5.6M17.5 14.8c2 .7 3.5 2.6 3.5 5.2"/>`,
  shootings:  `<circle cx="12" cy="12" r="8.5"/><path d="M12 3.5v8.5l7.4 4.3M12 12L4.6 16.3M12 12l7.4-4.3"/>`,
  kalender:   `<rect x="3" y="5" width="18" height="16" rx="2"/><path d="M8 3v4M16 3v4M3 10h18"/>`,
  medien:     `<path d="M4 7h3l1.5-2h7L17 7h3v13H4z"/><circle cx="12" cy="13" r="3.5"/>`,
  galerien:   `<rect x="7" y="3" width="14" height="14" rx="2"/><path d="M17 17v2a2 2 0 01-2 2H5a2 2 0 01-2-2V9a2 2 0 012-2h2"/>`,
  pakete:     `<path d="M21 8.5v7l-9 4.5-9-4.5v-7L12 4z"/><path d="M3 8.5 12 13l9-4.5M12 13v7"/>`,
  einst:      `<path d="M4 6h16M4 12h16M4 18h16"/><circle cx="9" cy="6" r="2"/><circle cx="15" cy="12" r="2"/><circle cx="8" cy="18" r="2"/>`,
}

const NAV = [
  { key:'uebersicht', label:'Übersicht',      href:'01-uebersicht.html' },
  { key:'anfragen',   label:'Anfragen',       href:'02-anfragen.html', count:11 },
  { key:'auftraege',  label:'Projekte',       href:'04-auftrag.html',  count:24 },
  { key:'shootings',  label:'Shootings',      count:9 },
  { key:'kontakte',   label:'Kontakte' },
  { key:'kalender',   label:'Kalender' },
  { lab:'Produktion' },
  { key:'medien',     label:'Medien',         href:'05-medien.html',   count:3 },
  { key:'galerien',   label:'Galerien' },
  { lab:'Studio' },
  { key:'pakete',     label:'Pakete' },
  { key:'einst',      label:'Einstellungen',  href:'14-einstellungen.html' },
]

/* the twelve screens, in flow order — drives prev/next in the footer strip */
const SCREENS = [
  { n:1,  t:'Übersicht',            href:'admin/01-uebersicht.html',  s:'Admin' },
  { n:2,  t:'Anfragen',             href:'admin/02-anfragen.html',    s:'Admin' },
  { n:3,  t:'Anfrage anlegen',      href:'admin/03-anfrage-neu.html', s:'Admin' },
  { n:4,  t:'Projekt',              href:'admin/04-auftrag.html',     s:'Admin' },
  { n:5,  t:'Medien-Upload',        href:'admin/05-medien.html',      s:'Admin' },
  { n:6,  t:'Startseite',           href:'portal/06-startseite.html', s:'Portal' },
  { n:7,  t:'Vertrag unterschreiben',href:'portal/07-vertrag.html',   s:'Portal' },
  { n:8,  t:'Galerie — Passwort',   href:'gallery/08-passwort.html',  s:'Galerie' },
  { n:9,  t:'Galerie — Raster',     href:'gallery/09-raster.html',    s:'Galerie' },
  { n:10, t:'Lightbox',             href:'gallery/10-lightbox.html',  s:'Galerie' },
  { n:11, t:'Download — PIN',       href:'gallery/11-pin.html',       s:'Galerie' },
  { n:12, t:'Download — E-Mail',    href:'gallery/12-email.html',     s:'Galerie' },
  { n:13, t:'Fragebogen-Vorlage',   href:'admin/13-fragebogen-builder.html', s:'Admin' },
  { n:14, t:'Einstellungen',        href:'admin/14-einstellungen.html', s:'Admin' },
]

function adminSidebar (active) {
  const items = NAV.map(i => {
    if (i.lab) return `<div class="navlab">${i.lab}</div>`
    const on = i.key === active ? ' on' : ''
    const ct = i.count ? `<span class="ct">${i.count}</span>` : ''
    const href = i.href || '#'
    return `<a class="nav${on}" href="${href}"><svg viewBox="0 0 24 24">${ICON[i.key]}</svg>${i.label} ${ct}</a>`
  }).join('')

  return `<nav class="side">
    <a class="brand" href="../index.html">Nordlicht</a>
    ${items}
    <div class="me">
      <img src="https://images.unsplash.com/photo-1500648767791-00dcc994a43e?w=80&q=70" onerror="this.removeAttribute('src')">
      Christian
    </div>
  </nav>`
}

/* screen number, surface, and movement through the set — reviewing a
   dozen files without this is where clickdummies die */
function dummybar (n, depth) {
  const up = depth === 2 ? '../' : ''
  const i = SCREENS.findIndex(s => s.n === Number(n))
  const cur = SCREENS[i], prev = SCREENS[i - 1], next = SCREENS[i + 1]
  const link = (s, label) => s
    ? `<a href="${up}${s.href}">${label}</a>`
    : `<span class="off">${label}</span>`

  return `<div class="dummybar">
    <span class="no">${String(cur.n).padStart(2,'0')} / ${SCREENS.length} · ${cur.s}</span>
    <b>${cur.t}</b>
    <span class="sp"></span>
    ${link(prev, '← zurück')}
    <a href="${up}index.html">Übersicht aller Screens</a>
    ${link(next, 'weiter →')}
  </div>`
}

/* project chrome for screen 04's detail tabs — header, stage line, tab row.
   Declared once here so a tab rename is a one-file change across seven pages. */
const PROJECT_TABS = [
  { key:'uebersicht',   label:'Übersicht',        href:'04-auftrag.html' },
  { key:'shootings',    label:'Shootings',        href:'04-shootings.html' },
  { key:'frageboegen',  label:'Fragebögen',       href:'04-frageboegen.html' },
  { key:'kommunikation',label:'Kommunikation',    href:'04-kommunikation.html' },
  { key:'angebot',      label:'Angebot & Vertrag',href:'04-angebot.html' },
  { key:'galerien',     label:'Galerien',         href:'04-galerien.html' },
]

function projectHead (active) {
  const tabs = PROJECT_TABS.map(t =>
    `<a class="${t.key === active ? 'on' : ''}" href="${t.href}">${t.label}</a>`).join('\n      ')
  return `
    <div class="hrow">
      <div>
        <div class="kick">Projekt · Hochzeit</div>
        <h1>Lena Meier &amp; Jonas Kowalski</h1>
      </div>
      <a class="txtbtn" href="#"><svg viewBox="0 0 24 24"><path d="M12 20h9"/><path d="M16.5 3.5a2.121 2.121 0 013 3L7 19l-4 1 1-4L16.5 3.5z"/></svg>Bearbeiten</a>
      <a class="btn" href="#"><svg viewBox="0 0 24 24"><path d="M4 4h16v13H8l-4 4z"/><path d="M8 9h8M8 13h5"/></svg>Ablauf senden</a>
    </div>
    <div class="meta row">
      <span class="d">Haupttermin 14.06.2026 · in 5 Tagen</span>
      <span class="v">4 Shootings · 03.2026 – 07.2026</span>
      <span class="g">80 Gäste</span>
    </div>
    <div class="stage">
      <span class="now"><span class="dot"></span>Vorbereitung</span>
      <span class="next">frühestes offenes Shooting · 2 von 4 geliefert</span>
      <a class="adv" href="#">Als fotografiert markieren →</a>
    </div>
    <div class="tabs">
      ${tabs}
    </div>`
}

document.addEventListener('DOMContentLoaded', () => {
  const body = document.body
  const depth = body.dataset.depth ? Number(body.dataset.depth) : 2

  const mount = document.querySelector('[data-sidebar]')
  if (mount) mount.outerHTML = adminSidebar(body.dataset.nav)

  const ph = document.querySelector('[data-projecthead]')
  if (ph) ph.outerHTML = projectHead(ph.dataset.projecthead)

  if (body.dataset.screen) body.insertAdjacentHTML('beforeend', dummybar(body.dataset.screen, depth))
})
