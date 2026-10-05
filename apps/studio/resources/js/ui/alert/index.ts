import type { VariantProps } from "class-variance-authority"
import { cva } from "class-variance-authority"

export { default as Alert } from "./Alert.vue"
export { default as AlertDescription } from "./AlertDescription.vue"
export { default as AlertTitle } from "./AlertTitle.vue"

export const alertVariants = cva(
  "relative w-full rounded-lg border px-4 py-3 text-sm grid has-[>svg]:grid-cols-[calc(var(--spacing)*4)_1fr] grid-cols-[0_1fr] has-[>svg]:gap-x-3 gap-y-0.5 items-start [&>svg]:size-4 [&>svg]:translate-y-0.5 [&>svg]:text-current",
  {
    variants: {
      variant: {
        default: "bg-card text-card-foreground",
        /* The bg-* group is one group to tailwind-merge, so the icon has to
           arrive as an arbitrary property or the fill drops it. */
        destructive:
          "flex items-center gap-3 rounded-[7px] border-0 bg-late-bg [background-image:var(--background-image-icon-alert)] bg-[length:13px_13px] bg-[position:9px_center] bg-no-repeat py-[7px] pr-[9px] pl-7 text-small leading-[1.3] font-medium text-destructive *:data-[slot=alert-description]:text-destructive/90",
      },
    },
    defaultVariants: {
      variant: "default",
    },
  },
)

export type AlertVariants = VariantProps<typeof alertVariants>
