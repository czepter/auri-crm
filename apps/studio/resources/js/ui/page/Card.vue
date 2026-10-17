<script setup lang="ts">
import { ref } from 'vue'
import { ChevronDown } from 'lucide-vue-next'
import { Button } from '@/ui/button'

/*
 * Ein Teil eines Falls: Titel, was drinsteht, und die eine Aktion, die dazu
 * gehört. Die Zusammenfassung ist der Grund, warum eine Karte zu sein darf —
 * eine Anzahl beantwortet nicht, ob man sie öffnen muss.
 *
 * Der Bearbeiten-Zustand steht als Modell nach außen, nicht nur als Ereignis:
 * ob nach dem Sichern geschlossen wird, weiß der Aufrufer, weil nur er weiß,
 * ob sein Request durchging.
 */
const props = withDefaults(
    defineProps<{
        title: string
        summary?: string | null
        defaultOpen?: boolean
        editable?: boolean
    }>(),
    { summary: null, defaultOpen: true, editable: false },
)

const emit = defineEmits<{ edit: []; cancel: []; save: [] }>()

const editing = defineModel<boolean>('editing', { default: false })

const open = ref(props.defaultOpen)

function start() {
    editing.value = true
    open.value = true
    emit('edit')
}

function cancel() {
    editing.value = false
    emit('cancel')
}
</script>

<template>
    <section data-slot="card" class="rounded-md border border-rule bg-background">
        <header
            class="flex h-[46px] cursor-pointer items-center gap-3 px-[18px]"
            @click="open = !open"
        >
            <h2 class="m-0 text-ui leading-[1.2] font-semibold tracking-[-0.015em]">
                {{ title }}
            </h2>
            <slot name="meta" />
            <span v-if="summary && !open" class="min-w-0 truncate text-ink-2">{{ summary }}</span>

            <div class="ml-auto flex flex-none items-center gap-3.5">
                <slot name="action" />

                <!-- Ohne .stop klappt jeder dieser Klicks die Karte zu: sie
                     liegen in der Kopfzeile, die selbst umschaltet. -->
                <template v-if="editable && open">
                    <a
                        v-if="!editing"
                        class="text-small tracking-[-0.01em] whitespace-nowrap text-action no-underline hover:underline"
                        href="#"
                        @click.stop.prevent="start"
                    >{{ $t('common.edit') }}</a>
                    <template v-else>
                        <a
                            class="text-small tracking-[-0.01em] whitespace-nowrap text-ink-2 no-underline hover:underline"
                            href="#"
                            @click.stop.prevent="cancel"
                        >{{ $t('common.cancel') }}</a>
                        <Button variant="pill" type="button" @click.stop="emit('save')">
                            {{ $t('common.save') }}
                        </Button>
                    </template>
                </template>

                <ChevronDown
                    class="size-[15px] shrink-0 text-ink-2 transition-transform duration-[150ms] ease-out"
                    :class="open && 'rotate-180'"
                />
            </div>
        </header>

        <div v-if="open" class="px-[18px] pb-[18px]">
            <slot v-if="editing" name="edit" />
            <slot v-else />
        </div>
    </section>
</template>
