import { reactive } from 'vue'
import { router } from '@inertiajs/vue3'
import { toast } from 'vue-sonner'
import { xsrfHeader } from '@/admin/lib/multipartUpload'

export const UNDO_WINDOW_MS = 5000

const hidden = reactive(new Set<string>())
const flushers = new Map<string, () => void>()
let listening = false

/** True from the click until the DELETE has answered; lists filter on it. */
export function isHidden(url: string) {
  return hidden.has(url)
}

function flushAll() {
  for (const flush of [...flushers.values()]) flush()
}

function listen() {
  if (listening) return
  listening = true
  // A visit would unmount the page and its toast; the delete is the user's decision, so it goes out first.
  router.on('before', ({ detail }) => {
    if (new URL(detail.visit.url).pathname !== window.location.pathname) flushAll()
  })
  window.addEventListener('pagehide', flushAll)
}

/** Hides the record, offers undo in a toast and sends DELETE `url` once the window closes, the page is left or the tab goes. */
export function deleteWithUndo(url: string, labels: { deleted: string; undo: string }) {
  if (hidden.has(url)) return
  listen()
  hidden.add(url)

  const send = (reload: boolean) => {
    clearTimeout(timer)
    flushers.delete(url)
    toast.dismiss(id)
    // keepalive lets the request outlive the page; manual redirect skips the page `back()` would send us to.
    fetch(url, { method: 'DELETE', keepalive: true, redirect: 'manual', headers: { ...xsrfHeader(), Accept: 'application/json' } }).finally(() => {
      hidden.delete(url)
      if (reload) router.reload()
    })
  }

  const timer = setTimeout(() => send(true), UNDO_WINDOW_MS)
  flushers.set(url, () => send(false))

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
