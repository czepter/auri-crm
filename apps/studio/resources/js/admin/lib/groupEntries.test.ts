import { expect, it } from "vitest";
import { groupEntries } from "./groupEntries";

const entry = (body: string, at: string, author: string | null = "Anna") => ({ body, author, at });

it("folds consecutive entries of one text, author and minute", () => {
    const rows = groupEntries([
        entry("Gebucht", "2026-11-06T10:15:01Z"),
        entry("Gebucht", "2026-11-06T10:15:40Z"),
        entry("Gebucht", "2026-11-06T10:16:00Z"),
        entry("Gebucht", "2026-11-06T10:16:10Z", "Ben"),
        entry("Fotografiert", "2026-11-06T10:16:20Z", "Ben"),
        entry("Gebucht", "2026-11-06T10:16:30Z", "Ben"),
    ]);

    expect(rows.map((row) => row.count)).toEqual([2, 1, 1, 1, 1]);
});
