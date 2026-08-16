/* Seitenleiste und Befehlspalette für alle CRM-Screens.
 *
 * Als <script src> geladen, nicht per fetch: eine lokale Datei hat einen
 * opaken Origin, fetch scheitert unter file://. Skript-Tags nicht.
 *
 * Der Screen sagt auf <body data-nav="anfragen">, welcher Eintrag aktiv ist.
 */

const NAV = [
  { key:'uebersicht', label:'Übersicht', href:'crm-uebersicht.html',
    icon:`<path d="M3 10.5 12 3l9 7.5"/><path d="M5 9.5V21h14V9.5"/>` },
  { key:'anfragen', label:'Anfragen', href:'crm-anfragen.html', count:12,
    icon:`<rect x="3" y="4" width="18" height="16" rx="2"/><path d="M8 8v8M13 8v5M18 8v10"/>` },
  { key:'projekte', label:'Projekte', count:24,
    icon:`<path d="M4 8h16v12H4z"/><path d="M9 8V5h6v3"/><path d="M4 13h16"/>` },
  { key:'kontakte', label:'Kontakte',
    icon:`<path d="M6 3h12v18H6z"/><circle cx="12" cy="10" r="2.4"/><path d="M8.7 17c.6-1.7 1.8-2.6 3.3-2.6s2.7.9 3.3 2.6"/><path d="M6 7H3.5M6 12H3.5M6 17H3.5"/>` },
  { key:'kalender', label:'Kalender',
    icon:`<rect x="3" y="5" width="18" height="16" rx="2"/><path d="M8 3v4M16 3v4M3 10h18"/>` },
  { key:'posteingang', label:'Posteingang', count:1,
    icon:`<path d="M3 6.5h18v11H3z"/><path d="m3 7.5 9 6 9-6"/>` },
  { lab:'Studio' },
  { key:'pakete', label:'Pakete',
    icon:`<path d="M21 8.5v7l-9 4.5-9-4.5v-7L12 4z"/><path d="M3 8.5 12 13l9-4.5M12 13v7"/>` },
  { key:'vorlagen', label:'Dokumentvorlagen',
    icon:`<path d="M6 3h7l5 5v13H6z"/><path d="M13 3v5h5"/><path d="M9 13h6M9 17h4"/>` },
  { key:'team', label:'Team',
    icon:`<circle cx="9" cy="8" r="3.2"/><path d="M3 20c0-3.3 2.7-5.5 6-5.5s6 2.2 6 5.5"/><path d="M16.5 5.5a3.2 3.2 0 0 1 0 5.6M17.5 14.8c2 .7 3.5 2.6 3.5 5.2"/>` },
  { key:'auswertungen', label:'Auswertungen',
    icon:`<path d="M3.5 20h17"/><path d="M7 20v-6M12 20V6M17 20v-9"/>` },
  { key:'einstellungen', label:'Einstellungen', href:'admin-einstellungen-portal.html',
    icon:`<path d="M4 6h16M4 12h16M4 18h16"/><circle cx="9" cy="6" r="2"/><circle cx="15" cy="12" r="2"/><circle cx="8" cy="18" r="2"/>` },
]

/* Zuletzt Besuchtes, gemischt in Besuchsreihenfolge. Nach Gattung gruppiert
   wäre es sortiert nach etwas, das niemand sucht. */
const RECENT = [
  ['Projekt', 'Meier & Kowalski · Hochzeit',       'vor 10 Minuten'],
  ['Kontakt', 'Familie Osterhoff',                 'vor 40 Minuten'],
  ['Galerie', 'Meier & Kowalski · Hochzeitstag',   'heute 11:20'],
  ['Anfrage', 'Sarah Brandt & Tim Erdmann',        'heute 09:05'],
  ['Projekt', 'Sophie Lang & Erik Voss · Hochzeit','gestern'],
  ['Kontakt', 'Nina Kaltenbach & Ruben Adler',     'gestern'],
  ['Galerie', 'Vehse & Rieck · Standesamt',        'Freitag'],
  ['Anfrage', 'Mira Sundermann',                   'Donnerstag'],
]

const svg = d => `<svg viewBox="0 0 24 24">${d}</svg>`

document.addEventListener('DOMContentLoaded', () => {
  const active = document.body.dataset.nav

  document.querySelector('[data-chrome]').outerHTML = `
    <nav class="side">
      <div class="wm">Nordlicht</div>
      <button class="find" onclick="palette.showModal()">
        ${svg(`<circle cx="11" cy="11" r="6.5"/><path d="m16 16 4.5 4.5"/>`)}Suchen <kbd>⌘K</kbd>
      </button>
      ${NAV.map(i => i.lab
        ? `<div class="group"></div><div class="lab">${i.lab}</div>`
        : `<a class="${i.key === active ? 'on' : ''}" href="${i.href || '#'}">${svg(i.icon)}${i.label}${
            i.count ? `<span class="ct">${i.count}</span>` : ''}</a>`).join('')}
      <div class="me"><span class="av">CB</span>Christian</div>
    </nav>

    <dialog class="palette" id="palette">
      <input class="q" type="search" placeholder="Kontakt, Anfrage, Projekt oder Galerie" autofocus>
      <div class="results">
        <div class="lab">Zuletzt</div>
        ${RECENT.map(([kind, name, when]) =>
          `<a href="#"><span class="kind">${kind}</span><span class="n">${name}</span><span class="m">${when}</span></a>`).join('')}
      </div>
    </dialog>`

  addEventListener('keydown', e => {
    if (e.key === 'k' && (e.metaKey || e.ctrlKey)) { e.preventDefault(); palette.showModal() }
  })
})
