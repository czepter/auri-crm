<script setup lang="ts">
import { useSlots } from 'vue'
import Page from './Page.vue'
import PageHead from './PageHead.vue'
import SaveState from './SaveState.vue'
import type { SaveState as State } from '@/admin/lib/autosave'

/*
 * Every settings, profile and create form opens in this shell: a centred
 * column of 720px with the head above it, the settings navigation on its left
 * and, where a preview exists, the preview sticky on its right. At 1920px the
 * form sits in the middle of the screen instead of hugging its left third.
 */
defineProps<{ title: string; kicker?: string; kickerHref?: string; state?: State }>()

const slots = useSlots()
</script>

<template>
    <Page data-tokens wide>
        <div class="min-w-0 flex-1">
            <div
                class="mx-auto w-full"
                :class="slots.preview ? 'max-w-[1540px]' : slots.nav ? 'max-w-[1040px]' : 'max-w-[780px]'"
            >
                <PageHead :kicker="kicker" :kickerHref="kickerHref" :title="title">
                    <SaveState v-if="state" class="ml-auto" :state="state" />
                    <slot name="head" />
                </PageHead>

                <div class="flex items-start gap-10 px-6 pt-6 pb-10 max-[640px]:flex-col max-[640px]:gap-4 max-[640px]:px-5">
                    <slot name="nav" />

                    <div class="w-full max-w-[720px] min-w-0 flex-1">
                        <slot />
                    </div>

                    <slot name="preview" />
                </div>
            </div>
        </div>
    </Page>
</template>
