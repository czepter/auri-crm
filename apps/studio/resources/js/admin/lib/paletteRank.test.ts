import { describe, expect, it } from "vitest"
import { initial, rank } from "./paletteRank"

const entry = (title: string, id = `/${title}`) => ({ id, title })

describe("rank", () => {
  it("puts word-start and consecutive matches first, keeps server-only matches last", () => {
    const rows = rank([], [entry("Anna Berger")], [entry("Hochzeit Berger"), entry("Beate Reiter"), entry("Mueller")], "berg")

    expect(rows.map((row) => row.title)).toEqual(["Anna Berger", "Hochzeit Berger", "Beate Reiter", "Mueller"])
  })

  it("keeps commands, then visit order without a query", () => {
    expect(rank([entry("Neue Anfrage")], [entry("b"), entry("a")], [], " ").map((row) => row.title)).toEqual(["Neue Anfrage", "b", "a"])
  })

  it("filters commands by the query next to the results", () => {
    const rows = rank([entry("Neue Anfrage"), entry("Neuer Kontakt")], [], [entry("Kontakt Berger Studio")], "kontakt")

    expect(rows.map((row) => row.title)).toEqual(["Neuer Kontakt", "Kontakt Berger Studio"])
  })

  it("drops a later command that repeats an earlier title", () => {
    const rows = rank([entry("Neues Angebot", "page"), entry("Neues Angebot", "global")], [], [], "")

    expect(rows.map((row) => row.id)).toEqual(["page"])
  })

  it("never defaults to a command that changes something", () => {
    const rows = rank([{ ...entry("Abschließen"), run: () => {} }, entry("Neue Anfrage")], [entry("Hochzeit")], [], "")

    expect(rows[0].title).toBe("Abschließen")
    expect(initial(rows)?.title).toBe("Neue Anfrage")
    expect(initial([{ ...entry("Abschließen"), run: () => {} }])).toBeUndefined()
  })
})
