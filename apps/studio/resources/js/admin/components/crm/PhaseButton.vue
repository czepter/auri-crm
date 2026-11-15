<script setup lang="ts">
import { computed } from 'vue'
import { ChevronDown } from 'lucide-vue-next'
import { Button } from '@/ui/button'
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger } from '@/ui/dropdown-menu'
import { laterStages } from '@/admin/lib/projectOverview'

const props = defineProps<{ stages: { value: string; label: string }[]; current: string | null }>()
const emit = defineEmits<{ select: [value: string] }>()

const next = computed(() => laterStages(props.stages, props.current)[0])
</script>

<template>
    <div v-if="stages.length" class="flex">
        <Button
            v-if="next"
            variant="pill-muted"
            type="button"
            class="rounded-r-none"
            @click="emit('select', next.value)"
        >
            {{ $t('project.nextPhase', { stage: next.label }) }}
        </Button>
        <DropdownMenu>
            <DropdownMenuTrigger as-child>
                <Button
                    variant="pill-muted"
                    type="button"
                    class="w-8 justify-center px-0"
                    :class="next && 'rounded-l-none border-l border-line-hover'"
                    :aria-label="$t('board.colStage')"
                >
                    <ChevronDown class="size-3.5" />
                </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end" class="w-[208px]">
                <DropdownMenuItem
                    v-for="stage in stages"
                    :key="stage.value"
                    :disabled="stage.value === current"
                    @select="emit('select', stage.value)"
                >
                    {{ stage.label }}
                </DropdownMenuItem>
            </DropdownMenuContent>
        </DropdownMenu>
    </div>
</template>
