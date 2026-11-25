import type { VariantProps } from "class-variance-authority"
import { cva } from "class-variance-authority"

export { default as Button } from "./Button.vue"

/*
 * Admin: default (filled action), secondary, outline, ghost (text action),
 * all 32px high with --r-sm; `size="icon"` makes any of them a 32px square.
 * `tone` is a modifier on top: `destructive` swaps the fill for --late.
 * The portal-* variants are the client portal's own and stay as they are.
 */
/*
 * The icon rules are on the base rather than on tokens.css's element reset:
 * that reset excludes [data-tokens], so a button's mark would arrive
 * unstroked on every screen that has moved to Tailwind.
 */
const PORTAL
  = "gap-2.5 rounded-[var(--r-pill)] px-[26px] py-[15px] text-[14.5px] font-semibold leading-none duration-200 ease-[var(--ease)] [&_svg]:size-[15px] [&_svg]:stroke-[1.6]"

export const buttonVariants = cva(
  "inline-flex shrink-0 cursor-pointer items-center gap-[7px] font-sans no-underline outline-none disabled:cursor-default disabled:opacity-50 [&_svg]:fill-none [&_svg]:stroke-current [&_svg]:stroke-[1.75] [&_svg]:[stroke-linecap:round] [&_svg]:[stroke-linejoin:round]",
  {
    variants: {
      variant: {
        default:
          "h-8 gap-1.5 rounded-[var(--r-sm)] border-0 bg-action px-3.5 text-small font-medium whitespace-nowrap text-action-foreground transition-[filter,background-color] duration-[.12s] ease-out hover:brightness-[1.08] disabled:bg-sunk-2 disabled:text-ink-2 disabled:opacity-100 [&_svg]:size-[15px]",
        secondary:
          "h-8 gap-1.5 rounded-[var(--r-sm)] border-0 bg-sunk-2 px-3.5 text-small font-medium whitespace-nowrap text-foreground transition-[filter,background-color] duration-[.12s] ease-out hover:bg-line-hover disabled:text-ink-2 disabled:opacity-100 [&_svg]:size-[15px]",
        outline:
          "h-8 gap-1.5 rounded-[var(--r-sm)] border border-line-hover bg-background px-3.5 text-small font-medium whitespace-nowrap text-foreground transition-colors duration-[.12s] ease-out hover:border-faint disabled:border-line-hover disabled:text-ink-2 disabled:opacity-100 [&_svg]:size-[15px]",
        ghost:
          "h-8 rounded-[var(--r-sm)] border-0 bg-transparent px-0 text-small font-medium leading-none text-muted-foreground hover:text-foreground disabled:text-ink-2 disabled:opacity-100 disabled:no-underline [&_svg]:size-[15px] [&_svg]:opacity-80",
        /*
         * mockups/redesign/tokens.css .btn and .btn.ghost — the portal's own
         * pair. Ink, not the warm accent: on that surface the accent is a
         * signal that something is open, and a button wearing it would read
         * as one more thing waiting. The warm fill is what it turns into
         * under the pointer, which is the one moment it is about to act.
         */
        portal: `${PORTAL} bg-foreground tracking-[-0.005em] text-background transition-[transform,background-color] hover:-translate-y-px hover:bg-action active:translate-y-0`,
        "portal-ghost": `${PORTAL} border border-line-hover bg-transparent text-foreground transition-colors hover:border-foreground hover:bg-foreground hover:text-background`,
        /* mockups/redesign/tokens.css .chip — the quiet portal action, and
           the only one that ever stands next to another of its kind. */
        "portal-chip":
          "gap-2 rounded-[var(--r-pill)] border border-line-hover bg-transparent px-[15px] py-2.5 text-[13px] leading-none font-medium text-foreground transition-colors duration-200 ease-[var(--ease)] hover:border-foreground hover:bg-foreground hover:text-background [&_svg]:size-3.5 [&_svg]:stroke-[1.5]",
        /*
         * A destination in a portal nav rather than an action: it inherits
         * the colour of the rail it stands in and answers on opacity, so the
         * same button reads on the header's ink and on the account page's
         * paper without a second variant for each.
         */
        "portal-nav":
          "border-0 bg-transparent p-0 text-left text-[13.5px] leading-none font-normal text-inherit opacity-72 transition-opacity hover:opacity-100 data-[active=true]:font-semibold data-[active=true]:opacity-100",
        /*
         * The chip's sibling for a filter row: same pill, but the fill marks
         * the one that is chosen rather than the one under the pointer, so
         * the two states cannot be confused. The `!` on border and text is
         * what beats the hover rule at equal specificity, and is the reason
         * the call sites carried it before this variant existed.
         */
        "portal-filter":
          "rounded-[var(--r-pill)] border border-line-hover px-[15px] py-2.5 text-[13px] leading-none font-medium text-muted-foreground transition-colors hover:border-foreground hover:text-foreground data-[active=true]:border-foreground! data-[active=true]:bg-foreground data-[active=true]:font-semibold data-[active=true]:text-background!",
      },
      size: {
        default: "",
        icon: "size-8 justify-center px-0 [&_svg]:size-4",
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
        class: "border-input text-foreground hover:border-foreground",
      },
      {
        variant: "default",
        tone: "destructive",
        class: "bg-destructive",
      },
    ],
    defaultVariants: {
      variant: "default",
      size: "default",
      tone: "default",
    },
  },
)

export type ButtonVariants = VariantProps<typeof buttonVariants>
