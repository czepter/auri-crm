import { router } from '@inertiajs/vue3'
import { toast } from 'vue-sonner'
import { UNDO_WINDOW_MS } from '@/admin/lib/deferredDelete'

/** Runs `url` at once and offers a toast whose undo action posts `undoUrl`. */
export function postWithUndo(url: string, undoUrl: string, labels: { done: string; undo: string }, data: Record<string, unknown> = {}) {
  router.post(url, data as Record<string, string>, {
    preserveScroll: true,
    onSuccess: () =>
      toast(labels.done, {
        duration: UNDO_WINDOW_MS,
        action: { label: labels.undo, onClick: () => router.post(undoUrl, {}, { preserveScroll: true }) },
      }),
  })
}

/** Ticks a task off with undo; on a task already done the route toggles it open again, which needs none. */
export function completeTask(task: { id: string; isDone: boolean }, labels: { done: string; undo: string }, data: Record<string, unknown> = {}) {
  const url = `/aufgaben/${task.id}/erledigt`

  if (task.isDone) router.post(url, data as Record<string, string>, { preserveScroll: true })
  else postWithUndo(url, `/aufgaben/${task.id}/wieder-oeffnen`, labels, data)
}
