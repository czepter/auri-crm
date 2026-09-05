/*
 * Which contacts the pane beside a contact shows, in the same three groups
 * C-15's table uses (#251) — who the studio is working with right now is the
 * question this screen is opened with, alphabetical order is not.
 */
import { fold } from "./search"

type Row = App.Crm.Data.ContactRowData

export const STANDINGS = ["running", "past", "none"] as const

export function groupContacts(contacts: Row[], term = "") {
    const needle = fold(term.trim())

    // A contact is looked up by how they are reached as often as by name.
    const found = contacts.filter(
        (row) =>
            !needle ||
            fold(
                `${row.name} ${row.email ?? ""} ${row.phone ?? ""} ${row.latestProject ?? ""}`,
            ).includes(needle),
    )

    return STANDINGS.map((key) => ({
        key,
        rows: found.filter((row) => row.standing === key),
    })).filter((group) => group.rows.length)
}
