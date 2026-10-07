import { type ClassValue, clsx } from 'clsx'
import { extendTailwindMerge } from 'tailwind-merge'

// Without this, text-small next to text-foreground reads as two colours and one is dropped.
const twMerge = extendTailwindMerge({
  extend: { classGroups: { 'font-size': [{ text: ['small', 'ui', 'body', 'title', 'heading'] }] } },
})

export function cn(...inputs: ClassValue[]) {
  return twMerge(clsx(inputs))
}
