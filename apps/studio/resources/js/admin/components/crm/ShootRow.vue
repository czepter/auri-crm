<script setup lang="ts">
/*
 * One shooting day as the case and the project screen read it: when, where,
 * who. The same row on both, so a change to it cannot land on one screen only.
 */
import { router } from "@inertiajs/vue3";
import { Ellipsis } from "lucide-vue-next";
import { Button } from "@/ui/button";
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger } from "@/ui/dropdown-menu";
import { dayWithWeekday } from "@/admin/lib/dates";

const props = defineProps<{ shoot: App.Crm.Data.ShootData; manageable?: boolean }>();

function advance(status: string) {
    router.post(`/shootings/${props.shoot.id}/status`, { status }, { preserveScroll: true });
}
</script>

<template>
    <div
        class="flex items-center gap-3.5 py-[11px] shadow-[inset_0_1px_0_var(--rule)] first:shadow-none max-[640px]:flex-wrap"
    >
        <span
            class="w-[150px] flex-none max-[640px]:w-auto max-[640px]:basis-full text-small leading-[1.47] font-semibold tracking-[-0.012em] tabular-nums"
        >
            {{ dayWithWeekday(shoot.date) }}
            <span class="block text-small leading-[1.47] font-normal text-ink-2">{{
                shoot.statusLabel
            }}</span>
        </span>
        <span class="min-w-0 flex-1 text-ui leading-[1.47] max-[640px]:basis-full">
            {{ shoot.label ?? shoot.kind }}
            <span v-if="shoot.venue" class="block text-small leading-[1.47] text-ink-2">{{ shoot.venue }}</span>
        </span>
        <span v-if="!$page.props.soloStudio" class="w-[118px] flex-none text-right text-small max-[640px]:w-auto max-[640px]:basis-full max-[640px]:text-left leading-[1.47] text-ink-2">{{
            shoot.crew.map((member) => member.name).join(", ") || "—"
        }}</span>
        <DropdownMenu v-if="manageable && shoot.forwardStatuses.length">
            <DropdownMenuTrigger as-child>
                <Button variant="ghost" type="button" class="px-2 max-[640px]:min-h-11 max-[640px]:min-w-11" :aria-label="$t('project.more')">
                    <Ellipsis class="size-4" />
                </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end" class="w-[208px]">
                <DropdownMenuItem v-for="status in shoot.forwardStatuses" :key="status.value" @select="advance(status.value)">
                    {{ status.label }}
                </DropdownMenuItem>
            </DropdownMenuContent>
        </DropdownMenu>
    </div>
</template>
