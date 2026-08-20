<template>
    <!-- Queue, case and trace stand side by side and each fills the height —
         as in mockups/redesign/crm-anfrage.html, where .queue, .detail and
         .rail are the flex children themselves. No wrapper of our own around
         them: one would take the stretch and leave the column at the height of
         its content, with the grey ending mid-screen. -->
    <div class="flex items-stretch" data-tokens="crm">
        <slot name="left" />
        <slot />
        <slot name="right" />
    </div>
</template>
