import { router } from "@inertiajs/vue3"

type Row = { id: string; open: boolean }

export function neighbour<T extends Row>(rows: T[], by: number): T | undefined {
    return rows[rows.findIndex((row) => row.open) + by]
}

// ↑↓ walks the queue without leaving the screen. Response time is the lever:
// eight days of silence happen when every step costs a page load. `rows` is
// what the pane's filter left standing, so the arrows reach what is on screen.
export function stepOnArrow(event: KeyboardEvent, rows: Row[], path: string) {
    if (event.key !== "ArrowUp" && event.key !== "ArrowDown") {
        return
    }

    // Not every keydown target is an element — one dispatched on the window
    // has no matches() — and an arrow key inside a field belongs to the field.
    const target = event.target as Element | null

    if (typeof target?.matches === "function" && target.matches("input, textarea, select")) {
        return
    }

    event.preventDefault()
    const next = neighbour(rows, event.key === "ArrowDown" ? 1 : -1)

    if (next) {
        // The pane keeps its filter in the URL; carrying it means a step does
        // not land on an unfiltered pane.
        router.get(`${path}/${next.id}${window.location.search}`, {}, { preserveScroll: true, preserveState: true })
    }
}
