import { router } from '@inertiajs/vue3'
import { onUnmounted, watch, type Ref } from 'vue'

// The event carries nothing; the mailbox reloads through its own controller.
// A hub that is down or a cookie that expired closes the source, and the page
// keeps working until the next visit.
export function useLiveMessages(url: Ref<string | null>): void {
  let source: EventSource | null = null

  watch(
    url,
    (next) => {
      source?.close()
      source = null

      if (next === null) return

      source = new EventSource(next, { withCredentials: true })
      source.onmessage = () => router.reload({ only: ['threads', 'openThread'] })
    },
    { immediate: true },
  )

  onUnmounted(() => source?.close())
}
