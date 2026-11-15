<script setup lang="ts">
import { ArrowUp } from 'lucide-vue-next'
import { Button } from '@/ui/button'

defineProps<{ placeholder: string; label: string; sendLabel: string; busy?: boolean; allowEmpty?: boolean }>()

const model = defineModel<string>({ default: '' })
const emit = defineEmits<{ send: [] }>()
</script>

<template>
    <div
        class="flex flex-col gap-2 rounded-[var(--r)] border border-input bg-background p-3 transition-[border-color] duration-[120ms] ease-out hover:border-line-hover focus-within:border-ring"
    >
        <textarea
            v-model="model"
            class="min-h-14 w-full resize-none border-0 bg-transparent p-0 text-ui leading-[1.5] text-foreground outline-none placeholder:text-faint"
            :placeholder="placeholder"
            :aria-label="label"
            @keydown.meta.enter="emit('send')"
            @keydown.ctrl.enter="emit('send')"
        />
        <div class="flex justify-end">
            <Button
                type="button"
                size="icon"
                :aria-label="sendLabel"
                :disabled="busy || (!allowEmpty && !model.trim())"
                @click="emit('send')"
            >
                <ArrowUp />
            </Button>
        </div>
    </div>
</template>
