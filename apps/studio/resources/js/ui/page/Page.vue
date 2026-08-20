<template>
    <div class="flex items-stretch justify-between" data-tokens="crm">
        <div v-if="$slots.left" class="shrink-0 max-w-64 pt-4">
            <slot name="left" />
        </div>

        <div class="flex-1 pt-4">
            <slot />
        </div>

        <!-- crm.css .rail, written out: 280px capped to max-w-64 by the column
             itself, and gone below 1080px, where the case outweighs its trace. -->
        <div
            v-if="$slots.right"
            class="w-70 max-w-64 shrink-0 overflow-y-auto bg-sunk px-5 py-4 max-[1080px]:hidden"
        >
            <slot name="right" />
        </div>
    </div>
</template>
