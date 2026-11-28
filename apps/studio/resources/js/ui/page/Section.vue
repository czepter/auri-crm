<script setup lang="ts">
/*
 * Ein Abschnitt einer Fallseite, der sich nicht aufklappen lässt: dieselbe
 * Fläche wie eine Karte, der Titel darüber. Neben den Karten las er sich
 * rahmenlos wie verwaist, und ein Titel links daneben ließ dem Inhalt zu
 * wenig Breite.
 */
defineProps<{ title: string }>()
</script>

<template>
    <section class="mt-3 surface-card px-6 pb-5 ">
        <h2 class="m-0 flex h-[52px] items-center text-[15px] leading-[1.2] font-semibold tracking-[-0.015em]">
            {{ title }}
        </h2>

        <div class="min-w-0">
            <div v-if="$slots.actions" class="mb-1.5 flex items-center gap-2.5">
                <slot name="actions" />
            </div>

            <slot />
        </div>
    </section>
</template>
