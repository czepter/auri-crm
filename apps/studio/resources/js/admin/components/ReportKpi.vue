<script setup lang="ts">
/* admin-extra.css .kpis / .kpi, forms.css .hint / .err — die Zahl steht als
   <b>, ihre Bezugsgroesse als <em>, der Fehlerfall als <i> in der Zahl. */
</script>

<template>
  <div class="rounded-md border border-border px-3.5 py-3 text-[11px] leading-[1.4] font-normal text-muted-foreground [&>b]:mb-0.5 [&>b]:block [&>b]:text-[19px] [&>b]:leading-[1.15] [&>b]:font-bold [&>b]:tracking-[-0.035em] [&>b]:tabular-nums [&>b]:text-foreground [&>b>i]:not-italic [&>b>i]:text-destructive [&>em]:not-italic [&>em]:text-faint">
    <slot />
  </div>
</template>
