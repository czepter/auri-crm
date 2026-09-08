import type { Slots } from 'vue'

// Do NOT wrap this in computed(() => Boolean(slots.right)) — it looks like
// the obvious tidy-up and silently freezes. `updateSlots` in
// @vue/runtime-core only calls `trigger(instance, 'set', '$slots')` inside
// its `isHmrUpdating` branch, so outside of dev/HMR a slot appearing or
// disappearing never fires a reactive trigger — a computed reading
// `slots.right` would compute once and freeze on that value forever in
// production. The owning component still force-reruns its render via the
// DYNAMIC_SLOTS patch flag whenever a parent's v-if-wrapped named slot
// changes (Posteingang.vue's #right is exactly this case), which is why
// reading `slots.right` directly here, on every call, stays correct — a
// computed would not. Regression coverage: hilfeDockt.test.ts, using a
// hand-written (uncompiled) component and vue's own createRenderer to force
// a second render of one live instance.
export function ermittleObDockt(slots: Slots, breitOhneRail: boolean, breitMitRail: boolean): boolean {
  return slots.right ? breitMitRail : breitOhneRail
}
