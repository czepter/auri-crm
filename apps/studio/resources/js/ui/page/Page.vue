<template>
    <div class="flex items-stretch justify-between" data-theme="crm">
        <div v-if="$slots.left" class="shrink-0 max-w-64 pt-4">
            <slot name="left" />
        </div>

        <div class="flex-1 pt-4">
            <slot />
        </div>

        <div v-if="$slots.right" class="shrink-0 max-w-64 pt-4! rail">
            <slot name="right" />
        </div>
    </div>
</template>
