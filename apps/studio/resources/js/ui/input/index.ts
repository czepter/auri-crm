import type { VariantProps } from "class-variance-authority"
import { cva } from "class-variance-authority"

export { default as Input } from "./Input.vue"

/*
 * The field, as forms.css draws it. Values are the .inp rule's own — this is
 * a port, not a redesign: the same 9px/11px padding, the same 13px type, the
 * same one-pixel accent border on focus. Written as utilities so a field can
 * be a component with a typed API, without the surface changing under it.
 *
 * `bg-background` rather than transparent because these fields sit on cards
 * that tint on hover, and a transparent input would tint with them.
 */
export const inputVariants = cva(
  [
    "w-full min-w-0 appearance-none rounded-md border border-input bg-background font-sans font-normal text-foreground",
    "transition-[border-color] duration-[120ms] ease-out",
    "placeholder:text-faint hover:border-line-hover",
    "focus:border-ring focus:outline-none",
    "disabled:cursor-not-allowed disabled:opacity-50",
    "aria-invalid:border-destructive",
    "file:border-0 file:bg-transparent file:font-medium file:text-foreground",
  ],
  {
    variants: {
      size: {
        /* forms.css .inp */
        default: "px-[11px] py-[9px] text-[13px] leading-[1.35]",
        /* forms.css select.inp.sm — the control that sits inside a row rather than a form */
        sm: "px-2 py-[5px] text-xs leading-[1.35]",
        /* forms.css .fld.lead .inp — the main date the whole intake hangs off */
        lead: "px-[13px] py-3 text-[15px] font-semibold leading-[1.2] tracking-[-0.01em]",
      },
    },
    defaultVariants: {
      size: "default",
    },
  },
)

export type InputVariants = VariantProps<typeof inputVariants>
