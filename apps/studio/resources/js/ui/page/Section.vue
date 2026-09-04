<script setup lang="ts">
/*
 * Ein Abschnitt einer Fallseite: der Titel links neben seinem Inhalt, ab
 * 1180px — dem Maß, auf das die Seiten ohnehin gebaut sind (--container-case).
 * Darunter rückt er darüber und wird größer, weil dann nur noch der Abstand
 * trennt.
 *
 * Keine Linie, weder über dem Abschnitt noch zwischen seinen Zeilen: die
 * Zeilen hatten bereits eine, und die zweite darüber machte aus der Seite ein
 * Gitter.
 */
defineProps<{ title: string }>()
</script>

<template>
    <section class="mt-10 min-[1180px]:mt-9 min-[1180px]:flex min-[1180px]:gap-6">
        <h2
            class="m-0 mb-2.5 text-[15px] leading-[1.2] font-semibold tracking-[-0.02em] min-[1180px]:mt-[5px] min-[1180px]:mb-0 min-[1180px]:w-28 min-[1180px]:flex-none min-[1180px]:text-[12.5px] min-[1180px]:tracking-[-0.012em] min-[1180px]:text-ink-2"
        >
            {{ title }}
        </h2>

        <div class="min-w-0 min-[1180px]:flex-1">
            <div v-if="$slots.actions" class="mb-1.5 flex items-center gap-2.5">
                <slot name="actions" />
            </div>

            <slot />
        </div>
    </section>
</template>
