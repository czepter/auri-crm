import type { LiveChannel, LiveTransport } from '@inertiajs/core'

type Source = {
  source: EventSource | null
  handlers: Map<string, Set<(payload: unknown) => void>>
  attached: WeakMap<EventSource, Set<string>>
}

function parse(data: string): unknown {
  try {
    return JSON.parse(data)
  } catch {
    return undefined
  }
}

export function mercure(hub: () => string | null): LiveTransport {
  const sources = new Map<string, Source>()
  const status = new Set<(connected: boolean) => void>()

  const notify = (connected: boolean) => status.forEach((callback) => callback(connected))

  // Event types are attached lazily: a source opened for one event gets the
  // next one added to it, not a second connection.
  function attach(record: Source, source: EventSource) {
    const done = record.attached.get(source) ?? new Set<string>()
    record.attached.set(source, done)

    record.handlers.forEach((handlers, event) => {
      if (done.has(event)) return

      done.add(event)
      source.addEventListener(event, (message) => {
        const payload = parse((message as MessageEvent<string>).data)
        handlers.forEach((handler) => handler(payload))
      })
    })
  }

  function open(name: string, record: Source) {
    if (sources.get(name) !== record) return

    // A closed source is one the hub refused, usually over an expired cookie;
    // the visit that got us here has just refreshed it.
    if (record.source === null || record.source.readyState === EventSource.CLOSED) {
      const url = hub()

      if (url === null) return

      record.source = new EventSource(`${url}?topic=${encodeURIComponent(name)}`, { withCredentials: true })
      record.source.addEventListener('open', () => notify(true))
      record.source.addEventListener('error', () => notify(false))
    }

    attach(record, record.source)
  }

  return {
    subscribe(channel: LiveChannel, event, handler) {
      const name = channel.name
      const record = sources.get(name) ?? { source: null, handlers: new Map(), attached: new WeakMap() }
      sources.set(name, record)

      const handlers = record.handlers.get(event) ?? new Set()
      handlers.add(handler)
      record.handlers.set(event, handlers)

      // Inertia subscribes while it swaps the page, before the framework's own
      // copy of that page (where the hub url lives) has been updated.
      queueMicrotask(() => open(name, record))

      return () => {
        handlers.delete(handler)
        if (handlers.size === 0) record.handlers.delete(event)

        if (record.handlers.size === 0) {
          record.source?.close()
          sources.delete(name)
        }
      }
    },

    onStatusChange(callback) {
      status.add(callback)

      return () => status.delete(callback)
    },
  }
}
