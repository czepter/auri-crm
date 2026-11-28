import type { Meta, StoryObj } from '@storybook/vue3-vite'
import preflight from 'tailwindcss/preflight.css?inline'
import tokens from '../../../../../../gallery/src/styles/tokens.css?inline'
import gallery from '../../../../../../gallery/src/styles/gallery.css?inline'
import photo from '../../../../../tests/e2e/shots/imgs/bild-04.jpg?inline'

// Die Galerie ist eine eigene Astro-App mit eigenem CSS. Im iframe bleibt es
// von admin.css getrennt, in derselben Reihenfolge wie apps/gallery/src/styles/app.css.
// Das Markup ist aus [galleryId].astro und download/[token].astro abgeschrieben.
const html = (body: string) => `<!doctype html><html lang="de"><head><style>${preflight}${tokens}${gallery}</style></head><body>${body}</body></html>`

const gate = (body: string, cover = true) =>
  html(`<section class="gate">${cover ? `<img src="${photo}" alt="" />` : ''}<div class="lock">${body}</div></section>`)

const arrow = '<svg viewBox="0 0 24 24"><path d="M12 4v12M6 11l6 6 6-6M5 20h14" /></svg>'

const tiles = Array.from({ length: 6 }, () => `<a class="tile" href="#" style="flex:1 1 30%;aspect-ratio:3/2"><img src="${photo}" alt="" /></a>`).join('')

const close = '<a class="close" href="#" aria-label="Schließen"><svg viewBox="0 0 24 24"><path d="M18 6 6 18M6 6l12 12" /></svg></a>'

// Die Galerie mit offenem Blatt über dem Raster.
const withPanel = (panel: string) =>
  html(`<div class="bar"><span class="who"><b>Preshoot</b></span><a class="barbtn solid" href="#">${arrow}Herunterladen</a></div>
<main class="wall">${tiles}</main><section class="signin" id="herunterladen">
<div class="sheethead"><h2>Herunterladen</h2>${close}</div>${panel}</section>`)

const email = '<div class="field"><label for="e">E-Mail</label><input id="e" type="email" required /></div>'
const code = (wrong: boolean) => `<div class="field"><label for="p">Download-Code</label><input id="p" type="text" inputmode="numeric" required${wrong ? ' aria-invalid="true"' : ''} />${wrong ? '<p class="err">Dieser Code stimmt nicht.</p>' : ''}</div>`
const sizes = `<fieldset class="sizes"><legend>Größe</legend>
  <label><input type="radio" name="size" value="original" checked />Original</label>
  <label><input type="radio" name="size" value="web" />Web</label>
</fieldset>`
const submit = '<button class="barbtn solid" type="submit">Download-Link erstellen</button>'

const meta = {
  title: 'Galerie/Anmeldung',
  parameters: { layout: 'fullscreen', controls: { disable: true } },
  args: { doc: '' },
  render: (args) => ({
    setup: () => ({ args }),
    template: '<iframe :srcdoc="args.doc" style="display:block;width:100%;height:100vh;border:0" />',
  }),
} satisfies Meta<{ doc: string }>

export default meta

type Story = StoryObj<typeof meta>

export const Herunterladen: Story = {
  args: { doc: withPanel(`<form>${email}${submit}</form>`) },
}

export const HerunterladenMitCode: Story = {
  name: 'Herunterladen, beide Größen und Code',
  args: {
    doc: withPanel(`<form>${sizes}${email}${code(true)}${submit}</form>`),
  },
}

export const LinkKommt: Story = {
  name: 'Der Link kommt',
  args: { doc: withPanel('<p>Der Link kommt per E-Mail an anna@example.test.</p>') },
}

const part = (label: string) => `<a class="barbtn solid" href="#">${label}</a>`
const line = (parts: string) => `<div><ul class="dl"><li>
  <span class="dl-name">Original</span><span class="dl-size">4,2 GB</span><span class="dl-parts">${parts}</span>
</li></ul></div>`
const head = '<h1 class="display">Preshoot</h1><p class="meta">27 Bilder</p>'

export const Teil: Story = {
  name: 'Link aus der Mail, ein Teil',
  args: { doc: gate(head + line(part('Herunterladen'))) },
}

export const Teile: Story = {
  name: 'Link aus der Mail, mehrere Teile',
  args: { doc: gate(head + line(part('Teil 1') + part('Teil 2') + part('Teil 3'))) },
}

export const Passwort: Story = {
  args: {
    doc: gate(`<h1 class="display">Preshoot</h1><div><form>
      <div class="field"><label for="pw">Galerie-Passwort</label><input id="pw" type="password" required /></div>
      <button class="btn" type="submit">Galerie öffnen <svg viewBox="0 0 24 24"><path d="M5 12h14M13 6l6 6-6 6" /></svg></button>
    </form><p class="hint">Steht in der Mail mit dem Link.</p></div>`, false),
  },
}
