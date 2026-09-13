import { describe, expect, it } from "vitest"
import { cn } from "@/lib/utils"
import { alertVariants } from "."

describe("alertVariants", () => {
  it("keeps fill and icon on destructive after the merge", () => {
    const classes = cn(alertVariants({ variant: "destructive" }))

    expect(classes).toContain("bg-late-bg")
    expect(classes).toContain("[background-image:var(--background-image-icon-alert)]")
  })
})
