<script setup lang="ts">
/*
 * Stripe's settings row: title and one noun in a column on the left, the
 * fields on the right. Below 560px the label stacks over the fields.
 */
defineProps<{ title: string; note?: string }>()
</script>

<template>
    <section class="@container border-t border-rule py-6 first:border-t-0 first:pt-0">
        <div class="grid gap-x-8 gap-y-3 @[560px]:grid-cols-[168px_minmax(0,1fr)]">
            <div>
                <h2 class="m-0 text-[15px] leading-[1.3] font-semibold tracking-[-0.015em]">{{ title }}</h2>
                <p v-if="note" class="m-0 mt-1 text-small leading-[1.45] text-ink-2">{{ note }}</p>
            </div>
            <div class="min-w-0"><slot /></div>
        </div>
    </section>
</template>
