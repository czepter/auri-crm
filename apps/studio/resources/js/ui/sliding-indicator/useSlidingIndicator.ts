import type { Ref } from "vue"
import { nextTick, onBeforeUnmount, onMounted, ref, watch } from "vue"

type Rect = { left: number; top: number; width: number; height: number }

// Inertia mounts a fresh page per tab, so the indicator would appear in place
// instead of travelling; the last rect per key is where it starts from.
const remembered = new Map<string, Rect>()

export function useSlidingIndicator(
  container: Ref<HTMLElement | null>,
  active: () => unknown,
  options: { selector?: string; key?: string } = {},
) {
  const selector = options.selector ?? "[aria-current], :checked + *"
  const rect = ref<Rect | null>(null)
  const animate = ref(false)
  let observer: ResizeObserver | undefined

  function measure() {
    const el = container.value?.querySelector<HTMLElement>(selector)
    if (!el) return null

    return { left: el.offsetLeft, top: el.offsetTop, width: el.offsetWidth, height: el.offsetHeight }
  }

  function update() {
    const next = measure()
    if (!next) return

    if (rect.value === null && options.key) {
      const from = remembered.get(options.key)
      if (from) {
        rect.value = from
        animate.value = false
        requestAnimationFrame(() => requestAnimationFrame(() => {
          animate.value = true
          rect.value = next
        }))
      }
      else rect.value = next
    }
    else {
      animate.value = rect.value !== null
      rect.value = next
    }

    if (options.key) remembered.set(options.key, next)
  }

  onMounted(() => {
    update()
    document.fonts?.ready.then(update)
    if (container.value && typeof ResizeObserver === "function") {
      observer = new ResizeObserver(() => {
        animate.value = false
        const next = measure()
        if (next) rect.value = next
      })
      observer.observe(container.value)
    }
  })
  onBeforeUnmount(() => observer?.disconnect())
  watch(active, () => nextTick(update))

  return { rect, animate }
}
