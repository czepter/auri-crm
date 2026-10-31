/*
 * Which inquiries the queue pane shows. The pane and the arrow keys beside it
 * both read this, so a filtered pane and ↑↓ never disagree about what is next.
 */
import { fold } from "./search"

type Row = App.Crm.Data.LeadQueueRowData
type Group = { key: string; rows: Row[] }

// Below this the wait is the normal course of an inquiry; marking it would
// mark every row.
export const NOTICEABLE_DAYS = 3

export function filterQueue(
    groups: Group[],
    term: string,
    waiting: boolean,
): Group[] {
    const needle = fold(term.trim())

    return groups.map((group) => ({
        ...group,
        rows: group.rows.filter(
            (row) =>
                (!waiting || row.waitingDays >= NOTICEABLE_DAYS) &&
                (!needle ||
                    fold(`${row.name} ${row.text ?? ""}`).includes(needle)),
        ),
    }))
}

// Same wording the board's table carries in its Zuletzt column.
export function since(days: number) {
    return days < 1
        ? "heute"
        : new Intl.RelativeTimeFormat("de-DE", { numeric: "auto" }).format(
              -days,
              "day",
          )
}
