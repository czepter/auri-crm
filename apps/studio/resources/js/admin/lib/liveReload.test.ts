import { beforeEach, expect, it, vi } from 'vitest'

const { reload, on } = vi.hoisted(() => ({
  reload: vi.fn(),
  on: vi.fn(),
}))
vi.mock('@inertiajs/vue3', () => ({ router: { reload, on } }))
vi.useFakeTimers()

class FakeEventSource {
  static CLOSED = 2
  static opened: FakeEventSource[] = []
  readyState = 1
  onmessage: (() => void) | null = null

  constructor(
    public url: string,
    public init?: EventSourceInit,
  ) {
    FakeEventSource.opened.push(this)
  }

  close() {
    this.readyState = FakeEventSource.CLOSED
  }
}

vi.stubGlobal('EventSource', FakeEventSource)

const { followLiveUpdates } = await import('./liveReload')

beforeEach(() => {
  followLiveUpdates(null)
  FakeEventSource.opened = []
  reload.mockClear()
})

it('opens one source per session and reloads on an event', () => {
  followLiveUpdates('/.well-known/mercure?topic=crm')
  followLiveUpdates('/.well-known/mercure?topic=crm')

  expect(FakeEventSource.opened).toHaveLength(1)
  expect(FakeEventSource.opened[0].init).toEqual({ withCredentials: true })

  FakeEventSource.opened[0].onmessage!()
  expect(reload).toHaveBeenCalledOnce()
})

it('skips the reload while a visit is under way', () => {
  const fire = (name: string, async: boolean) =>
    on.mock.calls.find(([event]) => event === name)![1]({ detail: { visit: { async } } })

  followLiveUpdates('/hub')
  vi.advanceTimersByTime(2000)

  fire('start', false)
  FakeEventSource.opened[0].onmessage!()
  fire('finish', false)
  expect(reload).not.toHaveBeenCalled()

  vi.advanceTimersByTime(2000)
  fire('start', true)
  FakeEventSource.opened[0].onmessage!()
  expect(reload).toHaveBeenCalledOnce()
})

it('closes on sign-out and reopens a source the hub refused', () => {
  followLiveUpdates('/hub')
  followLiveUpdates(null)
  expect(FakeEventSource.opened[0].readyState).toBe(FakeEventSource.CLOSED)

  followLiveUpdates('/hub')
  FakeEventSource.opened[1].close()
  followLiveUpdates('/hub')

  expect(FakeEventSource.opened).toHaveLength(3)
})
