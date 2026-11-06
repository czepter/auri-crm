<script setup lang="ts">
import { computed } from "vue";
import { router } from "@inertiajs/vue3";
import { ChevronDown } from "lucide-vue-next";
import { Button } from "@/ui/button";
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger } from "@/ui/dropdown-menu";
import { nextStep, paymentCall, stepRows } from "@/admin/lib/projectNextStep";

const props = defineProps<{
    header: App.Crm.Screens.CaseHeaderData;
    shoots: App.Crm.Data.ShootData[];
    people: App.Crm.Data.CasePersonData[];
}>();

function advance(shootId: string, status: string) {
    router.post(`/shootings/${shootId}/status`, { status }, { preserveScroll: true });
}

const step = computed(() => (props.header.canManageShoots ? nextStep(props.shoots) : null));
const stepMenu = computed(() => stepRows(props.shoots));
const callHref = computed(() => paymentCall(props.header.paymentPlan, props.people));
</script>

<template>
    <Button v-if="!step && callHref" variant="pill" as-child class="max-[640px]:min-h-11">
        <a :href="callHref">{{ $t("contacts.call") }}</a>
    </Button>
    <div v-else-if="step" class="flex">
        <Button variant="pill" type="button" class="rounded-r-none max-[640px]:min-h-11" @click="advance(step.shootId, step.status.value)">
            {{ step.status.label }}
        </Button>
        <DropdownMenu>
            <DropdownMenuTrigger as-child>
                <Button
                    variant="pill"
                    type="button"
                    class="rounded-l-none border-l border-white/30 px-2 max-[640px]:min-h-11 max-[640px]:min-w-11"
                    :aria-label="$t('board.colStage')"
                >
                    <ChevronDown class="size-3.5" />
                </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end" class="w-[260px]">
                <DropdownMenuItem v-for="row in stepMenu" :key="`${row.shootId}${row.status}`" @select="advance(row.shootId, row.status)">
                    {{ row.label }}
                </DropdownMenuItem>
            </DropdownMenuContent>
        </DropdownMenu>
    </div>
</template>
