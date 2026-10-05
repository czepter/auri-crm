import type { VariantProps } from "class-variance-authority"
import { cva } from "class-variance-authority"

export { default as Label } from "./Label.vue"

/*
 * The two captions this system has, ported from the rules that used to draw
 * them (forms.css `.fld > label` and `.cbrow > label`):
 *
 *   field  — the uppercase micro-label above a field. It is deliberately
 *            quiet and deliberately always present: forms.css's own comment
 *            says a filled form must still say what each value is, so the
 *            label never collapses into a placeholder.
 *   inline — the sentence beside a checkbox or radio, which is not a caption
 *            at all but the thing being switched, and reads as running text.
 *
 * `field` keeps the <em> the old rule styled — the "optional" mark that rides
 * along after a caption without shouting in uppercase with it.
 */
/* no select-none on the base: the portal wraps its field in the label, and a
   user-select:none inherited onto an <input> is a field whose value cannot be
   selected. Only the inline caption, which is clicked rather than read, takes it. */
export const labelVariants = cva("", {
  variants: {
    variant: {
      field: [
        "font-sans text-small font-semibold uppercase leading-none tracking-[0.06em] text-faint",
        "[&_em]:ml-1.5 [&_em]:text-small [&_em]:font-normal [&_em]:normal-case [&_em]:not-italic [&_em]:tracking-[0.02em]",
      ],
      inline: "cursor-pointer select-none font-sans text-[13px] font-normal leading-[1.4] text-foreground",
      /*
       * crm.css .field > span — the same caption on the admin's own screens,
       * where it is lighter and wider: the field below it is denser, and a
       * semibold caption at 10px would outweigh the value it names.
       */
      micro: "pl-0.5 font-sans text-small font-normal uppercase leading-none tracking-[0.05em] text-ink-2",
    },
  },
  defaultVariants: {
    variant: "field",
  },
})

export type LabelVariants = VariantProps<typeof labelVariants>
