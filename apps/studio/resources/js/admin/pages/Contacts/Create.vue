<script setup lang="ts">
import { Link, useForm } from '@inertiajs/vue3'
import AdminLayout from '@/admin/layouts/AdminLayout.vue'
import ContactPortalFields from '@/admin/components/ContactPortalFields.vue'
import Page from '@/ui/page/Page.vue'
import Card from '@/ui/page/Card.vue'
import { Button } from '@/ui/button'
import { Field } from '@/ui/field'
import { Input } from '@/ui/input'

defineProps<{ portalDefaults: Record<string, string | boolean | null> }>()

// P-09 (#228): '' is Standard, and the sheet's own encoding — the transform
// below drops it, so a contact is born with only the keys that depart.
const form = useForm({
  name: '', email: '', phone: '', street: '', postcode: '', city: '',
  portal: {
    portal_visible: '', accept_quotes: '', sign_contracts: '', payment_status: '',
    messaging_mode: '', gallery_downloads: '', gallery_lists: '', gallery_comments: '',
    welcome: '',
  },
})

function submit() {
  form
    .transform((data) => ({
      ...data,
      portal: Object.fromEntries(
        Object.entries(data.portal)
          .filter(([, value]) => value !== '')
          .map(([key, value]) => [key, value === '0' || value === '1' ? value === '1' : value]),
      ),
    }))
    .post('/kontakte')
}
</script>

<template>
  <AdminLayout active="kontakte">
    <Page data-tokens wide>
      <div class="min-w-0 flex-1">
        <div class="px-8 pt-[22px] max-[640px]:px-5 max-[640px]:pt-4">
          <div class="text-[11px] tracking-[0.05em] uppercase text-ink-2">
            <Link href="/kontakte">{{ $t('contacts.title') }}</Link>
          </div>
          <h1 class="m-0 mt-0.5 text-[32px] leading-[1.08] font-semibold tracking-[-0.032em] max-[640px]:text-[25px]">
            {{ $t('contacts.create') }}
          </h1>
        </div>

        <form class="flex max-w-[820px] flex-col gap-3 px-8 pt-5 pb-8 max-[640px]:px-5" @submit.prevent="submit">
          <Card :title="$t('contacts.reach')">
            <Field v-slot="{ id }" :label="$t('contacts.name')" :error="form.errors.name">
              <Input :id="id" v-model="form.name" type="text" autofocus />
            </Field>

            <div class="mt-3 flex gap-3 [&>*]:min-w-[148px] [&>*]:flex-1">
              <Field v-slot="{ id }" :label="$t('contacts.email')" :error="form.errors.email">
                <Input :id="id" v-model="form.email" type="email" autocomplete="email" />
              </Field>
              <Field v-slot="{ id }" :label="$t('contacts.phone')" :error="form.errors.phone">
                <Input :id="id" v-model="form.phone" type="tel" autocomplete="tel" />
              </Field>
            </div>
          </Card>

          <Card :title="$t('contacts.address')">
            <Field v-slot="{ id }" :label="$t('contacts.street')" :error="form.errors.street">
              <Input :id="id" v-model="form.street" type="text" autocomplete="street-address" />
            </Field>

            <div class="mt-3 flex gap-3">
              <Field v-slot="{ id }" class="w-[148px] flex-none" :label="$t('contacts.postcode')" :error="form.errors.postcode">
                <Input :id="id" v-model="form.postcode" type="text" autocomplete="postal-code" />
              </Field>
              <Field v-slot="{ id }" class="flex-1" :label="$t('contacts.city')" :error="form.errors.city">
                <Input :id="id" v-model="form.city" type="text" autocomplete="address-level2" />
              </Field>
            </div>
          </Card>

          <Card :title="$t('contacts.portalSection')">
            <ContactPortalFields :portal="form.portal" :defaults="portalDefaults" />
          </Card>

          <div class="mt-2 flex items-center gap-3 pt-4 shadow-[inset_0_1px_0_var(--rule)] [&>button:last-child]:ml-auto">
            <Link
              class="text-[12.5px] tracking-[-0.01em] whitespace-nowrap text-action no-underline hover:underline"
              href="/kontakte"
            >{{ $t('common.cancel') }}</Link>
            <Button variant="pill" type="submit" :disabled="form.processing">{{ $t('contacts.submit') }}</Button>
          </div>
        </form>
      </div>
    </Page>
  </AdminLayout>
</template>
