import { afterEach, beforeEach, describe, expect, it, vi } from "vitest"
import { effectScope, nextTick, ref } from "vue"
import { useSkeletonSwap } from "./useSkeletonSwap"

describe("useSkeletonSwap", () => {
  beforeEach(() => vi.useFakeTimers())
  afterEach(() => vi.useRealTimers())

  function setup() {
    const loading = ref(false)
    const visible = effectScope().run(() => useSkeletonSwap(loading))!

    return { loading, visible }
  }

  it("never paints for a load shorter than the delay", async () => {
    const { loading, visible } = setup()

    loading.value = true
    await nextTick()
    vi.advanceTimersByTime(100)
    loading.value = false
    await nextTick()
    vi.advanceTimersByTime(1000)

    expect(visible.value).toBe(false)
  })

  it("keeps a painted skeleton for the minimum time", async () => {
    const { loading, visible } = setup()

    loading.value = true
    await nextTick()
    vi.advanceTimersByTime(120)
    expect(visible.value).toBe(true)

    vi.advanceTimersByTime(100)
    loading.value = false
    await nextTick()
    vi.advanceTimersByTime(279)
    expect(visible.value).toBe(true)

    vi.advanceTimersByTime(1)
    expect(visible.value).toBe(false)
  })
})
