<script setup lang="ts">
/*
 * One shooting day as the case and the project screen read it: when, where,
 * who. The same row on both, so a change to it cannot land on one screen only.
 */
import { dayWithWeekday } from "@/admin/lib/dates";

defineProps<{ shoot: App.Crm.Data.ShootData }>();
</script>

<template>
    <div
        class="flex items-center gap-3.5 py-[11px] shadow-[inset_0_1px_0_var(--rule)] first:shadow-none max-[640px]:flex-wrap"
    >
        <span
            class="w-[150px] flex-none text-[13px] leading-[1.47] font-semibold tracking-[-0.012em] tabular-nums"
        >
            {{ dayWithWeekday(shoot.date) }}
            <span class="block text-[11.5px] leading-[1.47] font-normal text-ink-2">{{
                shoot.statusLabel
            }}</span>
        </span>
        <span class="min-w-0 flex-1 text-[13.5px] leading-[1.47]">
            {{ shoot.label ?? shoot.kind }}
            <span v-if="shoot.venue" class="text-[12px] leading-[1.47] text-ink-2">
                · {{ shoot.venue }}</span>
        </span>
        <span v-if="!$page.props.soloStudio" class="w-[118px] flex-none text-right text-[12px] leading-[1.47] text-ink-2">{{
            shoot.crew.map((member) => member.name).join(", ") || "—"
        }}</span>
    </div>
</template>
