import { describe, expect, it } from "vitest"
import { rank } from "./paletteRank"

const entry = (title: string) => ({ kind: "projekt", title, href: `/${title}` })

describe("rank", () => {
  it("puts word-start and consecutive matches first, keeps server-only matches last", () => {
    const rows = rank([entry("Anna Berger")], [entry("Hochzeit Berger"), entry("Beate Reiter"), entry("Mueller")], "berg")

    expect(rows.map((row) => row.title)).toEqual(["Anna Berger", "Hochzeit Berger", "Beate Reiter", "Mueller"])
  })

  it("keeps visit order without a query", () => {
    expect(rank([entry("b"), entry("a")], [], " ").map((row) => row.title)).toEqual(["b", "a"])
  })
})
