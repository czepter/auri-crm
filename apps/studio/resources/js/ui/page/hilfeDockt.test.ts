import { describe, expect, it } from 'vitest'
import { createRenderer, defineComponent, h, nextTick, ref } from 'vue'
import type { RendererOptions } from 'vue'
import { ermittleObDockt } from './hilfeDockt'

// environment: 'node' (vitest.config.ts) makes Vite compile every SFC
// SSR-only, so no .vue component here has a client `render` to mount, and
// renderToString is single-shot — neither can force a second render of one
// live instance to catch a `slots.right` read that got memoized. A plain
// .ts module has no SFC compile step to dodge, so it can be mounted with
// hand-written (uncompiled) components through vue's own createRenderer —
// a public API, not a new dependency — backed by a plain-object tree
// instead of the DOM. Minimal on purpose: only what render()/patch() calls.
type Knoten = { tag?: string; text?: string; children: Knoten[]; parent: Knoten | null }

function knoten(partial: Partial<Knoten> = {}): Knoten {
  return { children: [], parent: null, ...partial }
}

const nodeOps: RendererOptions<Knoten, Knoten> = {
  createElement: (tag) => knoten({ tag }),
  createText: (text) => knoten({ text }),
  createComment: (text) => knoten({ text }),
  setText: (node, text) => { node.text = text },
  setElementText: (node, text) => { node.children = [knoten({ text })] },
  insert: (child, parent, anchor) => {
    child.parent = parent
    const at = anchor ? parent.children.indexOf(anchor) : -1
    if (at < 0) parent.children.push(child)
    else parent.children.splice(at, 0, child)
  },
  remove: (child) => {
    if (!child.parent) return
    const i = child.parent.children.indexOf(child)
    if (i >= 0) child.parent.children.splice(i, 1)
    child.parent = null
  },
  parentNode: (node) => node.parent,
  nextSibling: (node) => {
    if (!node.parent) return null
    const i = node.parent.children.indexOf(node)
    return node.parent.children[i + 1] ?? null
  },
  patchProp: () => {},
}

const { render } = createRenderer(nodeOps)

describe('ermittleObDockt', () => {
  it('re-reads slots.right on every render of the same instance', async () => {
    const beobachtet: boolean[] = []
    const zeigeRight = ref(false)

    const Kind = defineComponent({
      setup(_props, { slots }) {
        return () => {
          beobachtet.push(ermittleObDockt(slots, false, true))
          return h('div')
        }
      },
    })

    const Wurzel = defineComponent({
      setup() {
        return () => h(Kind, null, zeigeRight.value ? { right: () => h('span') } : {})
      },
    })

    render(h(Wurzel), knoten())
    expect(beobachtet).toEqual([false])

    zeigeRight.value = true
    await nextTick()

    expect(beobachtet).toEqual([false, true])
  })
})
