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
        class="relative flex items-center gap-3.5 py-[11px] border-t border-rule first:border-t-0 max-[640px]:grid max-[640px]:grid-cols-[auto_1fr_auto] max-[640px]:gap-x-3 max-[640px]:gap-y-0 max-[640px]:pr-11"
    >
        <span
            class="w-[150px] flex-none text-small leading-[1.47] font-semibold tracking-[-0.012em] tabular-nums max-[640px]:contents"
        >
            <span class="max-[640px]:whitespace-nowrap">{{ dayWithWeekday(shoot.date) }}</span>
            <span class="block text-small leading-[1.47] font-normal text-ink-2 max-[640px]:col-start-3 max-[640px]:row-start-2 max-[640px]:text-right max-[640px]:whitespace-nowrap">{{
                shoot.statusLabel
            }}</span>
        </span>
        <span class="min-w-0 flex-1 text-ui leading-[1.47] max-[640px]:contents">
            <span class="max-[640px]:col-span-2 max-[640px]:truncate">{{ shoot.label ?? shoot.kind }}</span>
            <span v-if="shoot.venue" class="block text-small leading-[1.47] text-ink-2 max-[640px]:col-span-2 max-[640px]:col-start-1 max-[640px]:row-start-2 max-[640px]:truncate">{{ shoot.venue }}</span>
        </span>
        <span v-if="!$page.props.soloStudio" class="w-[118px] flex-none text-right text-small leading-[1.47] text-ink-2 max-[640px]:hidden">{{
            shoot.crew.map((member) => member.name).join(", ") || "—"
        }}</span>
        <DropdownMenu v-if="manageable && shoot.forwardStatuses.length">
            <DropdownMenuTrigger as-child>
                <Button variant="secondary" size="icon" type="button" class="max-[640px]:absolute max-[640px]:top-0 max-[640px]:right-0 max-[640px]:min-h-11 max-[640px]:min-w-11" :aria-label="$t('project.more')">
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
