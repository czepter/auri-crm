import type { Ref } from "vue"
import { onBeforeUnmount, ref, watch } from "vue"

// A load that returns inside `delay` never paints a skeleton; one that did
// paint stays for `minVisible`, so it reads as a state and not as a flicker.
export function useSkeletonSwap(loading: Ref<boolean>, { delay = 120, minVisible = 380 } = {}) {
  const visible = ref(false)
  let shownAt = 0
  let timer: ReturnType<typeof setTimeout> | undefined

  watch(loading, (now) => {
    clearTimeout(timer)

    if (now) {
      timer = setTimeout(() => {
        shownAt = Date.now()
        visible.value = true
      }, delay)
    }
    else if (visible.value) {
      timer = setTimeout(() => (visible.value = false), Math.max(0, minVisible - (Date.now() - shownAt)))
    }
  })

  onBeforeUnmount(() => clearTimeout(timer))

  return visible
}
