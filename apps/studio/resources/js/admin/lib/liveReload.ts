import { router } from '@inertiajs/vue3'
import { useThrottleFn } from '@vueuse/core'

// #357: an event carries nothing, so any change reloads the current page's
// props. Throttled because an upload of three hundred pictures is three
// hundred events.
const reload = useThrottleFn(() => router.reload(), 1000, true)

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
