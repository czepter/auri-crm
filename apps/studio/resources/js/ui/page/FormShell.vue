<script setup lang="ts">
import { useSlots } from 'vue'
import Page from './Page.vue'
import PageHead from './PageHead.vue'
import SaveState from './SaveState.vue'
import type { SaveState as State } from '@/admin/lib/autosave'

/*
 * Every settings, profile and create form opens in this shell: a column
 * of 720px with the head above it, the settings navigation on its left
 * and, where a preview exists, the preview sticky on its right. With a nav the
 * frame is the same on every page, so nothing moves between them. At 1920px the
 * form sits in the middle of the screen instead of hugging its left third.
 */
defineProps<{ title: string; kicker?: string; kickerHref?: string; state?: State; wide?: boolean }>()

const slots = useSlots()
</script>

<template>
    <Page data-tokens wide>
        <div class="min-w-0 flex-1">
            <div
                class="mx-auto w-full"
                :class="wide && !slots.aside ? 'max-w-[1480px]' : slots.nav || slots.preview ? 'max-w-[1540px]' : slots.aside ? 'max-w-[1140px]' : 'max-w-[780px]'"
            >
                <PageHead :kicker="kicker" :kickerHref="kickerHref" :title="title">
                    <SaveState v-if="state" class="ml-auto" :state="state" />
                    <slot name="head" />
                </PageHead>

                <div class="flex items-start gap-12 px-6 pt-6 pb-10 max-[640px]:flex-col max-[640px]:gap-4 max-[640px]:px-5">
                    <slot name="nav" />

                    <div class="w-full min-w-0" :class="wide && !slots.aside ? 'max-w-[1160px] flex-1' : slots.preview ? 'max-w-[560px] flex-none min-[1100px]:w-[560px] min-[1100px]:min-w-[520px]' : 'max-w-[720px] flex-none min-[1100px]:w-[720px]'">
                        <slot />
                    </div>

                    <slot name="preview" />
                    <aside v-if="slots.aside && !slots.preview" class="sticky top-6 w-[320px] flex-none max-[900px]:w-full">
                        <slot name="aside" />
                    </aside>
                </div>
            </div>
        </div>
    </Page>
</template>
