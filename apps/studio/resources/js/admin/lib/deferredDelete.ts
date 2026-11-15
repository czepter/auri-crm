import { reactive } from 'vue'
import { router } from '@inertiajs/vue3'
import { toast } from 'vue-sonner'
import { xsrfHeader } from '@/admin/lib/multipartUpload'

export const UNDO_WINDOW_MS = 5000

const hidden = reactive(new Set<string>())
const flushers = new Map<string, () => void>()
// Deleted on the server, still hidden until props fetched after the DELETE have landed.
const awaiting = new Set<string>()
let busy = 0
let listening = false

/** True from the click until fresh props without the record have landed; lists filter on it. */
export function isHidden(url: string) {
  return hidden.has(url)
}

function flushAll() {
  for (const flush of [...flushers.values()]) flush()
}

// A reload started while a visit is running would cancel it, and a visit started before the DELETE may carry the record.
function refresh() {
  if (!awaiting.size || busy) return
  const urls = [...awaiting]
  awaiting.clear()
  let cancelled = false
  router.reload({
    onCancel: () => (cancelled = true),
    onFinish: () => urls.forEach((url) => (cancelled ? awaiting.add(url) : hidden.delete(url))),
  })
}

function listen() {
  if (listening) return
  listening = true
  // A page change would unmount the toast; the delete is the user's decision, so it goes out first. Writes and same-page visits keep the window open.
  router.on('before', ({ detail }) => {
    const { method, url } = detail.visit
    if (method === 'get' && new URL(url).pathname !== window.location.pathname) flushAll()
  })
  router.on('start', () => busy++)
  router.on('finish', () => {
    busy = Math.max(0, busy - 1)
    refresh()
  })
  window.addEventListener('pagehide', flushAll)
}

/** Hides the record, offers undo in a toast and sends DELETE `url` once the window closes, the page is left or the tab goes. */
export function deleteWithUndo(url: string, labels: { deleted: string; undo: string; failed: string }) {
  if (hidden.has(url)) return
  listen()
  hidden.add(url)

  const send = () => {
    clearTimeout(timer)
    flushers.delete(url)
    toast.dismiss(id)
    const fail = () => {
      hidden.delete(url)
      toast.error(labels.failed)
    }
    // keepalive lets the request outlive the page; manual redirect skips the page `back()` would send us to.
    fetch(url, { method: 'DELETE', keepalive: true, redirect: 'manual', headers: { ...xsrfHeader(), Accept: 'application/json' } }).then((response) => {
      if (!response.ok && response.type !== 'opaqueredirect') return fail()
      awaiting.add(url)
      refresh()
    }, fail)
  }

  const timer = setTimeout(send, UNDO_WINDOW_MS)
  flushers.set(url, send)

  // Infinity: sonner pauses its own timer on hover, which would leave the toast up after the delete went out.
  const id = toast(labels.deleted, {
    duration: Infinity,
    action: {
      label: labels.undo,
      onClick: () => {
        clearTimeout(timer)
        flushers.delete(url)
        hidden.delete(url)
      },
    },
  })
}
