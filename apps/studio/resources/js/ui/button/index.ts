import type { VariantProps } from "class-variance-authority"
import { cva } from "class-variance-authority"

export { default as Button } from "./Button.vue"

/*
 * The three buttons the system actually has, ported from their rules rather
 * than redesigned:
 *
 *   default → tokens.css .btn      the filled, warm-accent button
 *   outline → admin-extra.css .abtn  the small stroked row action
 *   ghost   → tokens.css .txtbtn   the quiet inline action
 *
 * `tone` is the modifier those rules already carried as a second class —
 * .abtn.hot — kept as a compound so the base styling is not written out a
 * second time. `destructive` has no rule of its own to port: default is
 * already warm, and this tone only swaps that fill for --late, for the one
 * kind of press that cannot be undone.
 *
 * The `ml-auto` on default is .btn's own, and it is a layout rule living in a
 * component class: 45 call sites lean on the button pushing itself to the
 * right of its row. Dropping it here would move every one of them, so it
 * stays, and the places that opted out (.formfoot, .btn.wide) still opt out.
 */
/*
 * The icon rules are on the base rather than on tokens.css's element reset:
 * that reset excludes [data-tokens], so a button's mark would arrive
 * unstroked on every screen that has moved to Tailwind.
 */
const PORTAL
  = "gap-2.5 rounded-[var(--r-pill)] border-0 px-[26px] py-[15px] text-[14.5px] font-semibold leading-none duration-200 ease-[var(--ease)] [&_svg]:size-[15px] [&_svg]:stroke-[1.6]"

export const buttonVariants = cva(
  "inline-flex shrink-0 cursor-pointer items-center gap-[7px] font-sans no-underline outline-none disabled:cursor-default disabled:opacity-50 [&_svg]:fill-none [&_svg]:stroke-current [&_svg]:stroke-[1.75] [&_svg]:[stroke-linecap:round] [&_svg]:[stroke-linejoin:round]",
  {
    variants: {
      variant: {
        default:
          "ml-auto rounded-md border-0 bg-action px-[14px] py-[9px] text-[13px] font-semibold leading-none text-action-foreground [&_svg]:size-[15px]",
        outline:
          "flex-none rounded-[6px] border border-line-hover bg-background px-[9px] py-[3px] text-[10.5px] font-semibold leading-[1.4] text-muted-foreground hover:border-faint hover:text-foreground [&_svg]:size-[13px]",
        ghost:
          "border-0 bg-transparent px-0 py-[9px] text-[13px] font-medium leading-none text-muted-foreground hover:text-foreground [&_svg]:size-[15px] [&_svg]:opacity-80",
        /*
         * crm.css .btn and .btn-2 — the admin's own pair. A fourth and fifth
         * variant rather than a second Button: the admin presses the same
         * kind of button as the portal, it is only drawn denser and rounder
         * on a surface that stands open all day.
         */
        /*
         * mockups/redesign/tokens.css .btn and .btn.ghost — the portal's own
         * pair. Ink, not the warm accent: on that surface the accent is a
         * signal that something is open, and a button wearing it would read
         * as one more thing waiting. The warm fill is what it turns into
         * under the pointer, which is the one moment it is about to act.
         *
         * No ml-auto, unlike `default`: these sit where their layout puts
         * them.
         */
        portal: `${PORTAL} bg-foreground tracking-[-0.005em] text-background transition-[transform,background-color] hover:-translate-y-px hover:bg-action active:translate-y-0`,
        "portal-ghost": `${PORTAL} bg-transparent text-foreground shadow-[inset_0_0_0_1px_var(--line-hover)] transition-colors hover:bg-foreground hover:text-background hover:shadow-none`,
        /* mockups/redesign/tokens.css .chip — the quiet portal action, and
           the only one that ever stands next to another of its kind. */
        "portal-chip":
          "gap-2 rounded-[var(--r-pill)] border border-line-hover bg-transparent px-[15px] py-2.5 text-[13px] leading-none font-medium text-foreground transition-colors duration-200 ease-[var(--ease)] hover:border-foreground hover:bg-foreground hover:text-background [&_svg]:size-3.5 [&_svg]:stroke-[1.5]",
        pill: "gap-1.5 rounded-[var(--r-pill)] border-0 bg-action px-3.5 py-1.5 text-[12.5px] font-medium tracking-[-0.01em] whitespace-nowrap text-white transition-[filter,background-color] duration-[.12s] ease-out hover:brightness-[1.08]",
        "pill-muted":
          "gap-1.5 rounded-[var(--r-pill)] border-0 bg-sunk-2 px-3.5 py-1.5 text-[12.5px] font-medium tracking-[-0.01em] whitespace-nowrap text-foreground transition-[filter,background-color] duration-[.12s] ease-out hover:bg-[#E9E9EC]",
      },
      tone: {
        default: "",
        /* admin-extra.css .abtn.hot — the row action that is also the overdue mark */
        accent: "",
        /* the one kind of press that cannot be undone: cancel a shoot, erase DSGVO data */
        destructive: "",
      },
    },
    compoundVariants: [
      {
        variant: "outline",
        tone: "accent",
        class: "border-[#EAC9B8] text-action hover:border-action hover:text-action",
      },
      {
        variant: "default",
        tone: "destructive",
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
