import { router } from '@inertiajs/vue3'
import { useThrottleFn } from '@vueuse/core'
import { onUnmounted } from 'vue'

// A write publishes before its own response arrives. reload() would refetch
// the URL the visit is leaving, which 404s after a delete; the visit brings
// fresh props anyway.
let visiting = false
router.on('start', (event) => {
  if (!event.detail.visit.async) visiting = true
})
router.on('finish', (event) => {
  if (!event.detail.visit.async) visiting = false
})

// Shared chrome no event changes. The counts stay out of this list: a new
// lead moves the sidebar.
const STATIC_CHROME = ['brand', 'letterEditor', 'help', 'recent', 'release']

const registered = new Set<string[]>()

// A page, and any pane inside it, names the props that change under it; an
// event refetches the union instead of the whole page.
export function useLiveProps(keys: string[]): void {
  registered.add(keys)
  onUnmounted(() => registered.delete(keys))
}

// #357: an event carries nothing, so any change reloads the current page's
// props. Throttled because an upload of three hundred pictures is three
// hundred events.
const reload = useThrottleFn(() => {
  if (visiting) return

  router.reload(
    registered.size > 0 ? { only: ['nav', ...new Set([...registered].flat())] } : { except: STATIC_CHROME },
  )
}, 1000, true)

let source: EventSource | null = null

// A closed source is one the hub refused, usually over an expired cookie; the
// visit that called this has just refreshed it.
export function followLiveUpdates(url: string | null): void {
  if (url === null) {
    source?.close()
    source = null
    return
  }

  if (source !== null && source.readyState !== EventSource.CLOSED) return

  source = new EventSource(url, { withCredentials: true })
  source.onmessage = () => reload()
}
