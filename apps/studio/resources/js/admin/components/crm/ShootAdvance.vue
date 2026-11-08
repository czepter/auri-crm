<script setup lang="ts">
import { computed } from "vue";
import { router } from "@inertiajs/vue3";
import { ChevronDown, Ellipsis } from "lucide-vue-next";
import { Button } from "@/ui/button";
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger } from "@/ui/dropdown-menu";

const props = defineProps<{ shoot: App.Crm.Data.ShootData }>();

const archive = computed(() => props.shoot.forwardStatuses.find((s) => s.value === "archived"));
const onward = computed(() => props.shoot.forwardStatuses.filter((s) => s.value !== "archived"));

function advance(status: string) {
    router.post(`/shootings/${props.shoot.id}/status`, { status }, { preserveScroll: true });
}
</script>

<template>
    <div v-if="onward.length" class="flex flex-none">
        <Button variant="pill-muted" type="button" class="min-h-10 rounded-r-none max-[640px]:min-h-11" @click="advance(onward[0].value)">
            {{ onward[0].label }}
        </Button>
        <DropdownMenu>
            <DropdownMenuTrigger as-child>
                <Button
                    variant="pill-muted"
                    type="button"
                    class="min-h-10 min-w-10 justify-center rounded-l-none border-l border-line px-2 max-[640px]:min-h-11 max-[640px]:min-w-11"
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
    <DropdownMenu v-else-if="archive">
        <DropdownMenuTrigger as-child>
            <Button variant="ghost" type="button" class="min-h-10 min-w-10 justify-center px-2 max-[640px]:min-h-11 max-[640px]:min-w-11" :aria-label="$t('project.more')">
                <Ellipsis class="size-4" />
            </Button>
        </DropdownMenuTrigger>
        <DropdownMenuContent align="end" class="w-[208px]">
            <DropdownMenuItem @select="advance(archive.value)">{{ archive.label }}</DropdownMenuItem>
        </DropdownMenuContent>
    </DropdownMenu>
</template>
