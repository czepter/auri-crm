<script setup lang="ts">
import { computed } from "vue";
import { router } from "@inertiajs/vue3";
import { ChevronDown } from "lucide-vue-next";
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
    <div v-if="onward.length" class="flex flex-none gap-px">
        <Button variant="secondary" type="button" class="rounded-r-none max-[640px]:min-h-11" @click="advance(onward[0].value)">
            {{ onward[0].label }}
        </Button>
        <DropdownMenu>
            <DropdownMenuTrigger as-child>
                <Button
                    variant="secondary"
                    size="icon"
                    type="button"
                    class="rounded-l-none max-[640px]:min-h-11 max-[640px]:min-w-11"
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
    <Button v-else-if="archive" variant="secondary" type="button" class="max-[640px]:min-h-11" @click="advance(archive.value)">
        {{ archive.label }}
    </Button>
</template>
