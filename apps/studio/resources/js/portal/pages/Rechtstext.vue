<script setup lang="ts">
import { Link } from '@inertiajs/vue3'
import PortalFooter from '@/portal/components/PortalFooter.vue'
import PortalHeader from '@/portal/components/PortalHeader.vue'
import PortalShell from '@/portal/components/PortalShell.vue'
import PortalWrap from '@/portal/components/PortalWrap.vue'

// Der Körper kommt aus DocumentTemplate::body, denselben Weg wie Angebot und
// Rechnung ihn nehmen — serverseitig aus den Bausteinen gerendert und beim
// Speichern bereinigt, nicht hier.
defineProps<{ title: string; body: string; addressee: string[] }>()
</script>

<template>
  <PortalShell>
    <PortalHeader />

    <PortalWrap class="pb-40">
      <section class="pt-[var(--band)]">
        <div class="animate-rise motion-reduce:animate-none mb-4 flex justify-end">
          <Link class="text-[13.5px] text-muted-foreground hover:text-foreground max-[700px]:inline-flex max-[700px]:min-h-11 max-[700px]:items-center" href="/">
            {{ $t('portal.toBooking') }}
          </Link>
        </div>
        <h1 class="animate-rise [animation-delay:140ms] motion-reduce:animate-none font-serif text-[clamp(36px,5vw,52px)] leading-[var(--display-leading)] font-normal tracking-[var(--display-tracking)]">
          {{ title }}
        </h1>
      </section>

      <article
        class="animate-rise [animation-delay:230ms] motion-reduce:animate-none mt-12 max-w-[62ch] text-[15.5px] leading-[1.6] [&_h2]:mt-8 [&_h2]:mb-2 [&_h2]:text-[17px] [&_h2]:leading-[1.35] [&_h2]:font-semibold [&_h2:first-child]:mt-0 [&_p+*]:mt-3 [&_ul]:list-disc [&_ul]:pl-5 [&_ol]:list-decimal [&_ol]:pl-5"
        v-html="body"
      />

      <address v-if="addressee.length" class="mt-6 max-w-[62ch] text-[15.5px] leading-[1.6] not-italic">
        <span v-for="line in addressee" :key="line" class="block">{{ line }}</span>
      </address>
    </PortalWrap>

    <PortalFooter />
  </PortalShell>
</template>
