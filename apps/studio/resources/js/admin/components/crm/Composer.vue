<script setup lang="ts">
import { ArrowUp } from 'lucide-vue-next'

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
            <button
                type="button"
                class="grid size-10 cursor-pointer place-items-center rounded-full bg-action text-action-foreground disabled:cursor-default disabled:opacity-50"
                :aria-label="sendLabel"
                :disabled="busy || (!allowEmpty && !model.trim())"
                @click="emit('send')"
            >
                <ArrowUp class="size-5" />
            </button>
        </div>
    </div>
</template>
