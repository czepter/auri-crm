<script setup lang="ts">
/*
 * One shooting day as the case and the project screen read it: when, where,
 * who. The same row on both, so a change to it cannot land on one screen only.
 */
import { router } from "@inertiajs/vue3";
import { ChevronDown, Ellipsis } from "lucide-vue-next";
import { Button } from "@/ui/button";
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger } from "@/ui/dropdown-menu";
import { dayWithWeekday } from "@/admin/lib/dates";
import { computed } from "vue";

const props = defineProps<{ shoot: App.Crm.Data.ShootData; manageable?: boolean }>();

const archive = computed(() => props.shoot.forwardStatuses.find((s) => s.value === "archived"));
const onward = computed(() => props.shoot.forwardStatuses.filter((s) => s.value !== "archived"));

function advance(status: string) {
    router.post(`/shootings/${props.shoot.id}/status`, { status }, { preserveScroll: true });
}
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
        <div v-if="manageable && onward.length" class="flex flex-none">
            <Button variant="pill" type="button" class="rounded-r-none" @click="advance(onward[0].value)">
                {{ onward[0].label }}
            </Button>
            <DropdownMenu>
                <DropdownMenuTrigger as-child>
                    <Button
                        variant="pill"
                        type="button"
                        class="rounded-l-none border-l border-white/30 px-2"
                        :aria-label="$t('board.colStage')"
                    >
                        <ChevronDown class="size-3.5" />
                    </Button>
                </DropdownMenuTrigger>
                <DropdownMenuContent align="end" class="w-[208px]">
                    <DropdownMenuItem v-for="status in onward" :key="status.value" @select="advance(status.value)">
                        {{ status.label }}
                    </DropdownMenuItem>
                    <DropdownMenuItem v-if="archive" @select="advance(archive.value)">{{ archive.label }}</DropdownMenuItem>
                </DropdownMenuContent>
            </DropdownMenu>
        </div>
        <DropdownMenu v-else-if="manageable && archive">
            <DropdownMenuTrigger as-child>
                <Button variant="ghost" type="button" class="px-2" :aria-label="$t('project.more')">
                    <Ellipsis class="size-4" />
                </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end" class="w-[208px]">
                <DropdownMenuItem @select="advance(archive.value)">{{ archive.label }}</DropdownMenuItem>
            </DropdownMenuContent>
        </DropdownMenu>
    </div>
</template>
