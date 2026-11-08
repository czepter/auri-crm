<script setup lang="ts">
/*
 * One shooting day as the case and the project screen read it: when, where,
 * who. The same row on both, so a change to it cannot land on one screen only.
 */
import ShootAdvance from "@/admin/components/crm/ShootAdvance.vue";
import { dayWithWeekday } from "@/admin/lib/dates";

defineProps<{ shoot: App.Crm.Data.ShootData; manageable?: boolean }>();
</script>

<template>
    <div
        class="flex items-center gap-3.5 py-[11px] shadow-[inset_0_1px_0_var(--rule)] first:shadow-none max-[640px]:flex-wrap"
    >
        <span
            class="w-[150px] flex-none text-small leading-[1.47] font-semibold tracking-[-0.012em] tabular-nums"
        >
            {{ dayWithWeekday(shoot.date) }}
            <span class="block text-small leading-[1.47] font-normal text-ink-2">{{
                shoot.statusLabel
            }}</span>
        </span>
        <span class="min-w-0 flex-1 text-ui leading-[1.47]">
            {{ shoot.label ?? shoot.kind }}
            <span v-if="shoot.venue" class="block text-small leading-[1.47] text-ink-2">{{ shoot.venue }}</span>
        </span>
        <span v-if="!$page.props.soloStudio" class="w-[118px] flex-none text-right text-small max-[640px]:w-auto max-[640px]:text-left leading-[1.47] text-ink-2">{{
            shoot.crew.map((member) => member.name).join(", ") || "—"
        }}</span>
        <ShootAdvance v-if="manageable" :shoot="shoot" />
    </div>
</template>
