import { expect, it } from 'vitest'

const sources = import.meta.glob<string>('../**/*.vue', { query: '?raw', import: 'default', eager: true })

const INPUT = 'raw <input>: use ui/input, ui/textarea or ui/input-group'
const BUTTON = 'raw <button> styled as a button: use <Button>'

const ALLOW: Record<string, string> = {
  [`admin/components/crm/ProjectFields.vue ${INPUT}`]: 'borderless tag entry inside the chip box',
  [`admin/pages/Settings/AutomationRule.vue ${INPUT}`]: 'title editor that reads as the page heading',
  [`portal/components/QuoteSlider.vue ${INPUT}`]: 'range slider, no primitive',
  [`admin/components/gallery/GalleryDesignFields.vue ${BUTTON}`]: 'colour swatch, the fill is the content',
  [`admin/components/quote/ShowcaseImages.vue ${BUTTON}`]: '20px remove badge on a thumbnail corner',
  [`portal/components/GalleryLightbox.vue ${BUTTON}`]: 'glass arrows over the photo',
  [`portal/pages/Gallery/Show.vue ${BUTTON}`]: 'glass heart over the photo',
}

function tags(source: string, name: string) {
  const found: { line: number; text: string }[] = []
  const open = new RegExp(`<${name}(?=[\\s/>])`, 'g')
  for (let m = open.exec(source); m; m = open.exec(source)) {
    let i = m.index + m[0].length
    let quote = ''
    for (; i < source.length; i++) {
      const c = source[i]
      if (quote) { if (c === quote) quote = '' }
      else if (c === '"' || c === "'") quote = c
      else if (c === '>') break
    }
    found.push({ line: source.slice(0, m.index).split('\n').length, text: source.slice(m.index, i + 1) })
  }
  return found
}

const RULES: { name: string; tag: string; bad: RegExp; ok?: RegExp; also?: RegExp }[] = [
  { name: 'raw <select>: use ui/select', tag: 'select', bad: /./ },
  { name: 'raw checkbox/radio: use ui/checkbox or ui/radio-group', tag: 'input', bad: /type=["'](checkbox|radio)["']/ },
  { name: 'raw <input>: use ui/input, ui/textarea or ui/input-group', tag: 'input', bad: /./, ok: /type=["'](hidden|file)["']/ },
  { name: 'raw <button> styled as a button: use <Button>', tag: 'button', bad: /rounded/, ok: /text-left|w-full|data-drag/, also: /(?<![:\w-])(bg-(?!transparent|\[?none)|border(?![-\w])|border-(?!0)|hover:bg-)/ },
  { name: 'hand-rolled segmented rail: use ChoiceGroup', tag: '(div|nav|span)', bad: /bg-sunk[^"']*p-\[3px\]|p-\[3px\][^"']*bg-sunk/ },
  { name: 'Button height/radius override', tag: 'Button', bad: /(?<![:\w-])(h-9|h-10|min-h-10|rounded-md|rounded-lg|rounded-full|text-ui)(?![\w-])/ },
  { name: 'single-choice row: use ChoiceGroup', tag: 'Button', bad: /:variant="[^"]*===\s*[\w.]+\.(key|value|id|slug)\b[^"]*\?\s*'[a-z-]+'\s*:\s*'[a-z-]+'/ },
  { name: 'removed Button variant', tag: 'Button', bad: /variant="(pill|pill-muted|segmented)"/ },
]

const ROLES = /role=["'](switch|tab|tablist)["']/

it('uses the control set instead of hand-rolled controls', () => {
  const offenders: string[] = []
  for (const [path, source] of Object.entries(sources)) {
    const file = path.replace(/^\.\.\//, '')
    if (file.startsWith('./')) continue
    const allowed = (rule: string) => ALLOW[`${file} ${rule}`] !== undefined
    for (const rule of RULES) {
      for (const { line, text } of tags(source, rule.tag)) {
        const hit = rule.bad.test(text) && !rule.ok?.test(text) && (rule.also?.test(text) ?? true)
        if (hit && !allowed(rule.name)) offenders.push(`${file}:${line} ${rule.name}`)
      }
    }
    for (const { line, text } of tags(source, '[a-zA-Z]+')) {
      if (ROLES.test(text) && !allowed('role')) offenders.push(`${file}:${line} role switch/tab: use ui/switch or tabs`)
    }
  }
  expect(offenders).toEqual([])
})
