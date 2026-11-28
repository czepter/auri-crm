import { describe, expect, it, vi } from 'vitest'

const toastMock = vi.hoisted(() => vi.fn((..._args: unknown[]) => 'toast-1'))
const post = vi.hoisted(() => vi.fn((..._args: unknown[]) => {}))

vi.mock('vue-sonner', () => ({ toast: toastMock }))
vi.mock('@inertiajs/vue3', () => ({ router: { post } }))
vi.mock('@/admin/lib/deferredDelete', () => ({ UNDO_WINDOW_MS: 5000 }))

import { postWithUndo } from './undoable'

describe('postWithUndo', () => {
  it('posts at once and undoes through the counterpart route', () => {
    postWithUndo('/aufgaben/1/erledigt', '/aufgaben/1/wieder-oeffnen', { done: 'Erledigt', undo: 'Rückgängig' })

    const [url, , options] = post.mock.calls[0] as [string, unknown, { onSuccess: () => void }]
    expect(url).toBe('/aufgaben/1/erledigt')
    expect(toastMock).not.toHaveBeenCalled()

    options.onSuccess()
    const [message, toastOptions] = toastMock.mock.calls[0] as [string, { action: { onClick: () => void } }]
    expect(message).toBe('Erledigt')

    toastOptions.action.onClick()
    expect(post.mock.calls[1][0]).toBe('/aufgaben/1/wieder-oeffnen')
  })
})
