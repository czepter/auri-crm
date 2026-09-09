// environment: 'node' (see vitest.config.ts) has no Storage — jsdom would
// pull in a full DOM for one API. helpPanel persists to localStorage, so
// tests need it; a Map-backed stand-in is enough for get/set/remove/clear.
class MemoryStorage implements Storage {
  private store = new Map<string, string>()

  get length() {
    return this.store.size
  }

  clear() {
    this.store.clear()
  }

  getItem(key: string) {
    return this.store.get(key) ?? null
  }

  key(index: number) {
    return [...this.store.keys()][index] ?? null
  }

  removeItem(key: string) {
    this.store.delete(key)
  }

  setItem(key: string, value: string) {
    this.store.set(key, String(value))
  }
}

globalThis.localStorage = new MemoryStorage()
