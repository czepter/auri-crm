import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'

const toastMock = vi.hoisted(() => Object.assign(vi.fn((..._args: unknown[]) => 'toast-1'), { dismiss: vi.fn() }))
let onBefore: (event: { detail: { visit: { url: string } } }) => void = () => {}

vi.mock('@/admin/lib/multipartUpload', () => ({ xsrfHeader: () => ({ 'X-XSRF-TOKEN': 'tok3n' }) }))
vi.mock('vue-sonner', () => ({ toast: toastMock }))
vi.mock('@inertiajs/vue3', () => ({
  router: { reload: vi.fn(), on: vi.fn((_: string, cb: typeof onBefore) => (onBefore = cb)) },
}))

const labels = { deleted: 'Gelöscht', undo: 'Rückgängig' }
const fetchMock = vi.fn(() => Promise.resolve(new Response(null)))

async function load() {
  vi.resetModules()
  return import('./deferredDelete')
}

beforeEach(() => {
  vi.useFakeTimers()
  vi.stubGlobal('fetch', fetchMock)
  vi.stubGlobal('window', { location: { pathname: '/aufgaben' }, addEventListener: vi.fn() })
})

afterEach(() => {
  vi.useRealTimers()
  vi.unstubAllGlobals()
  vi.clearAllMocks()
})

describe('deleteWithUndo', () => {
  it('hides at once and sends DELETE after the window', async () => {
    const { deleteWithUndo, isHidden, UNDO_WINDOW_MS } = await load()

    deleteWithUndo('/aufgaben/1', labels)
    expect(isHidden('/aufgaben/1')).toBe(true)
    expect(fetchMock).not.toHaveBeenCalled()

    await vi.advanceTimersByTimeAsync(UNDO_WINDOW_MS)
    expect(fetchMock).toHaveBeenCalledWith('/aufgaben/1', expect.objectContaining({ method: 'DELETE', keepalive: true }))
    expect(isHidden('/aufgaben/1')).toBe(false)
  })

  it('never sends after undo', async () => {
    const { deleteWithUndo, isHidden, UNDO_WINDOW_MS } = await load()

    deleteWithUndo('/aufgaben/1', labels)
    const options = toastMock.mock.calls[0]?.[1] as { action: { onClick: () => void } }
    options.action.onClick()
    await vi.advanceTimersByTimeAsync(UNDO_WINDOW_MS * 2)

    expect(isHidden('/aufgaben/1')).toBe(false)
    expect(fetchMock).not.toHaveBeenCalled()
  })

  it('sends at once when the page is left', async () => {
    const { deleteWithUndo } = await load()

    deleteWithUndo('/aufgaben/1', labels)
    onBefore({ detail: { visit: { url: 'http://localhost/auftraege/2' } } })
    expect(fetchMock).toHaveBeenCalledTimes(1)

    await vi.advanceTimersByTimeAsync(10_000)
    expect(fetchMock).toHaveBeenCalledTimes(1)
  })

  it('keeps waiting on a visit to the same page', async () => {
    const { deleteWithUndo } = await load()

    deleteWithUndo('/aufgaben/1', labels)
    onBefore({ detail: { visit: { url: 'http://localhost/aufgaben?tab=done' } } })

    expect(fetchMock).not.toHaveBeenCalled()
  })
})
