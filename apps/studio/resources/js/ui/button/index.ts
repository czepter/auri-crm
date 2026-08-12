import type { VariantProps } from "class-variance-authority"
import { cva } from "class-variance-authority"

export { default as Button } from "./Button.vue"

/*
 * The three buttons the system actually has, ported from their rules rather
 * than redesigned:
 *
 *   default → tokens.css .btn      the filled ink button
 *   outline → admin-extra.css .abtn  the small stroked row action
 *   ghost   → tokens.css .txtbtn   the quiet inline action
 *
 * `tone` is the modifier those rules already carried as a second class —
 * .abtn.hot and .btn.warm — kept as a compound so the base styling is not
 * written out a second time.
 *
 * The `ml-auto` on default is .btn's own, and it is a layout rule living in a
 * component class: 45 call sites lean on the button pushing itself to the
 * right of its row. Dropping it here would move every one of them, so it
 * stays, and the places that opted out (.formfoot, .btn.wide) still opt out.
 */
export const buttonVariants = cva(
  "inline-flex shrink-0 cursor-pointer items-center gap-[7px] font-sans no-underline outline-none disabled:cursor-default disabled:opacity-50",
  {
    variants: {
      variant: {
        default:
          "ml-auto rounded-md border-0 bg-foreground px-[14px] py-[9px] text-[13px] font-semibold leading-none text-background [&_svg]:size-[15px]",
        outline:
          "flex-none rounded-[6px] border border-line-hover bg-background px-[9px] py-[3px] text-[10.5px] font-semibold leading-[1.4] text-muted-foreground hover:border-faint hover:text-foreground [&_svg]:size-[13px]",
        ghost:
          "border-0 bg-transparent px-0 py-[9px] text-[13px] font-medium leading-none text-muted-foreground hover:text-foreground [&_svg]:size-[15px] [&_svg]:opacity-80",
      },
      tone: {
        default: "",
        /* admin-extra.css .abtn.hot — the row action that is also the overdue mark */
        accent: "",
        /* portal.css .btn.warm — the portal's one accent-filled action */
        warm: "",
      },
    },
    compoundVariants: [
      {
        variant: "outline",
        tone: "accent",
        class: "border-[#EAC9B8] text-destructive hover:border-destructive hover:text-destructive",
      },
      {
        variant: "default",
        tone: "warm",
        class: "bg-destructive",
      },
    ],
    defaultVariants: {
      variant: "default",
      tone: "default",
    },
  },
)

export type ButtonVariants = VariantProps<typeof buttonVariants>
