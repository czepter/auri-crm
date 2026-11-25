import { beforeEach, expect, it, vi } from 'vitest'
import { mercure } from './mercureLive'

class FakeEventSource {
  static CLOSED = 2
  static opened: FakeEventSource[] = []
  readyState = 1
  listeners = new Map<string, ((event: unknown) => void)[]>()

  constructor(
    public url: string,
    public init?: EventSourceInit,
  ) {
    FakeEventSource.opened.push(this)
  }

  addEventListener(type: string, listener: (event: unknown) => void) {
    this.listeners.set(type, [...(this.listeners.get(type) ?? []), listener])
  }

  emit(type: string, data = '{}') {
    this.listeners.get(type)?.forEach((listener) => listener({ data }))
  }

  close() {
    this.readyState = FakeEventSource.CLOSED
  }
}

vi.stubGlobal('EventSource', FakeEventSource)

const channel = { name: 'crm/1', type: 'public' }
const flush = () => Promise.resolve()

beforeEach(() => {
  FakeEventSource.opened = []
})

it('shares one source per channel and routes by event type', async () => {
  const transport = mercure(() => '/hub')
  const changed = vi.fn()
  const other = vi.fn()

  transport.subscribe(channel, 'changed', changed)
  transport.subscribe(channel, 'other', other)
  await flush()

  expect(FakeEventSource.opened).toHaveLength(1)
  expect(FakeEventSource.opened[0].url).toBe('/hub?topic=crm%2F1')
  expect(FakeEventSource.opened[0].init).toEqual({ withCredentials: true })

  FakeEventSource.opened[0].emit('changed', '{"a":1}')
  expect(changed).toHaveBeenCalledWith({ a: 1 })
  expect(other).not.toHaveBeenCalled()

  FakeEventSource.opened[0].emit('changed', 'nope')
  expect(changed).toHaveBeenLastCalledWith(undefined)
})

it('closes the source when the last subscriber leaves', async () => {
  const transport = mercure(() => '/hub')
  const first = transport.subscribe(channel, 'changed', vi.fn())
  const second = transport.subscribe(channel, 'other', vi.fn())
  await flush()

  first()
  expect(FakeEventSource.opened[0].readyState).toBe(1)
  second()
  expect(FakeEventSource.opened[0].readyState).toBe(FakeEventSource.CLOSED)
})

it('reopens a source the hub refused on the next subscription', async () => {
  const transport = mercure(() => '/hub')
  transport.subscribe(channel, 'changed', vi.fn())
  await flush()

  FakeEventSource.opened[0].close()
  transport.subscribe(channel, 'other', vi.fn())
  await flush()

  expect(FakeEventSource.opened).toHaveLength(2)
})

it('does nothing without a hub', async () => {
  const transport = mercure(() => null)

  expect(() => transport.subscribe(channel, 'changed', vi.fn())()).not.toThrow()
  await flush()
  expect(FakeEventSource.opened).toHaveLength(0)
})

it('reports connection changes from open and error', async () => {
  const transport = mercure(() => '/hub')
  const status = vi.fn()
  transport.onStatusChange!(status)
  transport.subscribe(channel, 'changed', vi.fn())
  await flush()

  FakeEventSource.opened[0].emit('error')
  FakeEventSource.opened[0].emit('open')

  expect(status.mock.calls).toEqual([[false], [true]])
})
