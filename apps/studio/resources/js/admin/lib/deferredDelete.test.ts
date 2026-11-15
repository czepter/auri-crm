import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'

type Visit = { method: string; url: string }
type ReloadOptions = { onCancel?: () => void; onFinish?: () => void }

const toastMock = vi.hoisted(() => Object.assign(vi.fn((..._args: unknown[]) => 'toast-1'), { dismiss: vi.fn(), error: vi.fn() }))
const handlers: Record<string, (event: { detail: { visit: Visit } }) => void> = {}
const reload = vi.hoisted(() => vi.fn((_options?: unknown) => {}))

vi.mock('@/admin/lib/multipartUpload', () => ({ xsrfHeader: () => ({ 'X-XSRF-TOKEN': 'tok3n' }) }))
vi.mock('vue-sonner', () => ({ toast: toastMock }))
vi.mock('@inertiajs/vue3', () => ({
  router: { reload, on: vi.fn((name: string, cb: (typeof handlers)[string]) => (handlers[name] = cb)) },
}))

const labels = { deleted: 'Gelöscht', undo: 'Rückgängig', failed: 'Nicht gelöscht' }
const fetchMock = vi.fn((..._args: unknown[]) => Promise.resolve(new Response(null)))

const visit = (method: string, url: string) => ({ detail: { visit: { method, url } } })

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
  fetchMock.mockImplementation(() => Promise.resolve(new Response(null)))
})

describe('deleteWithUndo', () => {
  it('hides at once and sends DELETE after the window', async () => {
    const { deleteWithUndo, isHidden, UNDO_WINDOW_MS } = await load()

    deleteWithUndo('/aufgaben/1', labels)
    expect(isHidden('/aufgaben/1')).toBe(true)
    expect(fetchMock).not.toHaveBeenCalled()

    await vi.advanceTimersByTimeAsync(UNDO_WINDOW_MS)
    expect(fetchMock).toHaveBeenCalledWith('/aufgaben/1', expect.objectContaining({ method: 'DELETE', keepalive: true }))
  })

  it('stays hidden until the reload has landed', async () => {
    const { deleteWithUndo, isHidden, UNDO_WINDOW_MS } = await load()

    deleteWithUndo('/aufgaben/1', labels)
    await vi.advanceTimersByTimeAsync(UNDO_WINDOW_MS)

    expect(reload).toHaveBeenCalledTimes(1)
    expect(isHidden('/aufgaben/1')).toBe(true)

    ;(reload.mock.calls[0]?.[0] as ReloadOptions).onFinish?.()
    expect(isHidden('/aufgaben/1')).toBe(false)
  })

  it('reloads again when the reload was cancelled', async () => {
    const { deleteWithUndo, isHidden, UNDO_WINDOW_MS } = await load()

    deleteWithUndo('/aufgaben/1', labels)
    await vi.advanceTimersByTimeAsync(UNDO_WINDOW_MS)
    const first = reload.mock.calls[0]?.[0] as ReloadOptions
    first.onCancel?.()
    first.onFinish?.()
    handlers.finish?.(visit('get', '/aufgaben'))

    expect(isHidden('/aufgaben/1')).toBe(true)
    expect(reload).toHaveBeenCalledTimes(2)
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

  it('sends at once on a GET to another page and reloads once that visit is done', async () => {
    const { deleteWithUndo, isHidden } = await load()

    deleteWithUndo('/aufgaben/1', labels)
    handlers.before?.(visit('get', 'http://localhost/auftraege/2'))
    handlers.start?.(visit('get', 'http://localhost/auftraege/2'))
    expect(fetchMock).toHaveBeenCalledTimes(1)

    await vi.advanceTimersByTimeAsync(10_000)
    expect(fetchMock).toHaveBeenCalledTimes(1)
    expect(reload).not.toHaveBeenCalled()
    expect(isHidden('/aufgaben/1')).toBe(true)

    handlers.finish?.(visit('get', 'http://localhost/auftraege/2'))
    expect(reload).toHaveBeenCalledTimes(1)
  })

  it('keeps waiting on a visit to the same page', async () => {
    const { deleteWithUndo } = await load()

    deleteWithUndo('/aufgaben/1', labels)
    handlers.before?.(visit('get', 'http://localhost/aufgaben?tab=done'))

    expect(fetchMock).not.toHaveBeenCalled()
  })

  it('keeps waiting on a POST to another path', async () => {
    const { deleteWithUndo } = await load()

    deleteWithUndo('/aufgaben/1', labels)
    handlers.before?.(visit('post', 'http://localhost/notizen'))

    expect(fetchMock).not.toHaveBeenCalled()
  })

  it.each([
    ['a non-2xx answer', () => Promise.resolve(new Response(null, { status: 500 }))],
    ['a network error', () => Promise.reject(new TypeError('offline'))],
  ])('restores the row after %s', async (_, answer) => {
    const { deleteWithUndo, isHidden, UNDO_WINDOW_MS } = await load()
    fetchMock.mockImplementationOnce(answer)

    deleteWithUndo('/aufgaben/1', labels)
    await vi.advanceTimersByTimeAsync(UNDO_WINDOW_MS)

    expect(isHidden('/aufgaben/1')).toBe(false)
    expect(toastMock.error).toHaveBeenCalledWith('Nicht gelöscht')
    expect(reload).not.toHaveBeenCalled()
  })
})
