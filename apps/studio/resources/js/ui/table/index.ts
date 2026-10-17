import type { VariantProps } from "class-variance-authority"
import { cva } from "class-variance-authority"

export { default as Table } from "./Table.vue"
export { default as TableCell } from "./TableCell.vue"
export { default as TableGroupRow } from "./TableGroupRow.vue"
export { default as TableHead } from "./TableHead.vue"
export { default as TableRow } from "./TableRow.vue"

/*
 * The admin's list, ported from the rules crm.css set on td and th directly.
 * Six screens wrote the same eight class lists out — a table is a table, and
 * the flavours of a cell are a name, a muted value and a figure on the right.
 *
 * `align: right` also switches on tabular figures: everything that is right
 * aligned here is a number or a date, and a column of them has to line up.
 */
export const tableCellVariants = cva(
  "px-6 first:pl-8 last:pr-8 py-3 align-baseline text-ui leading-[1.47] first:rounded-l-lg last:rounded-r-lg",
  {
    variants: {
      tone: {
        default: "",
        /* the row's own name — the cell one reads first and clicks */
        name: "text-ui font-semibold tracking-[-0.014em]",
        muted: "text-ink-2",
      },
      align: {
        left: "",
        right: "text-right tabular-nums",
      },
    },
    defaultVariants: { tone: "default", align: "left" },
  },
)

export type TableCellVariants = VariantProps<typeof tableCellVariants>
