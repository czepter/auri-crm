import { beforeEach, describe, expect, it, vi } from "vitest"
import { router } from "@inertiajs/vue3"
import { neighbour, stepOnArrow } from "./queueStep"

vi.mock("@inertiajs/vue3", () => ({ router: { get: vi.fn() } }))
vi.stubGlobal("window", { location: { search: "?q=ma" } })

const rows = [
    { id: "a", open: false },
    { id: "b", open: true },
    { id: "c", open: false },
]

const key = (name: string, target: unknown = null) => ({ key: name, target, preventDefault: vi.fn() }) as unknown as KeyboardEvent

describe("queueStep", () => {
    beforeEach(() => vi.mocked(router.get).mockClear())

    it("finds the row beside the open one", () => {
        expect(neighbour(rows, 1)?.id).toBe("c")
        expect(neighbour(rows, -1)?.id).toBe("a")
        expect(neighbour(rows.slice(1), 1)?.id).toBe("c")
        expect(neighbour(rows.slice(0, 2), 1)).toBeUndefined()
    })

    it("visits it with the filter kept", () => {
        stepOnArrow(key("ArrowDown"), rows, "/auftraege")

        expect(router.get).toHaveBeenCalledWith("/auftraege/c?q=ma", {}, { preserveScroll: true, preserveState: true })
    })

    it("leaves arrows inside a field alone", () => {
        stepOnArrow(key("ArrowDown", { matches: () => true }), rows, "/anfragen")

        expect(router.get).not.toHaveBeenCalled()
    })
})
