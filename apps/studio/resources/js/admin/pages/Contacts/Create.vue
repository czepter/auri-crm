<script setup lang="ts">
import { Link, useForm } from '@inertiajs/vue3'
import AdminLayout from '@/admin/layouts/AdminLayout.vue'
import ContactPortalFields from '@/admin/components/crm/ContactPortalFields.vue'
import ContactSocialProfiles from '@/admin/components/crm/ContactSocialProfiles.vue'
import FormShell from '@/ui/page/FormShell.vue'
import CreateSummary from '@/admin/components/CreateSummary.vue'
import Card from '@/ui/page/Card.vue'
import { Button } from '@/ui/button'
import { Field } from '@/ui/field'
import { Input } from '@/ui/input'
import { useLiveProps } from '@/admin/lib/liveReload'

defineProps<{
  portalDefaults: Record<string, string | boolean | null>
  socialPlatforms: { value: string; label: string }[]
}>()
useLiveProps([])

// P-09 (#228): '' is Standard, and the sheet's own encoding — the transform
// below drops it, so a contact is born with only the keys that depart.
const form = useForm({
  name: '', email: '', phone: '', street: '', postcode: '', city: '',
  social_profiles: [] as { platform: string; value: string }[],
  portal: {
    portal_visible: '', accept_quotes: '', sign_contracts: '', payment_status: '',
    messaging_mode: '', gallery_downloads: '', gallery_lists: '', gallery_comments: '',
    offers: '',
    welcome_inquiry: '', welcome_active: '', welcome_done: '',
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
    <FormShell :kicker="$t('contacts.title')" kicker-href="/kontakte" :title="$t('contacts.create')" wide>
      <form class="flex max-w-[720px] flex-col gap-3" @submit.prevent="submit">
        <Card :title="$t('contacts.reach')">
          <Field v-slot="{ id }" :label="$t('contacts.name')" :error="form.errors.name">
            <Input :id="id" v-model="form.name" type="text" autofocus />
          </Field>

          <div class="mt-3 flex flex-wrap gap-3 [&>*]:min-w-[148px] [&>*]:flex-1">
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

        <Card :title="$t('contacts.profiles')">
          <div class="flex flex-col gap-3">
            <ContactSocialProfiles :profiles="form.social_profiles" :platforms="socialPlatforms" :errors="form.errors" />
          </div>
        </Card>

        <Card :title="$t('contacts.portalSection')">
          <ContactPortalFields :portal="form.portal" :defaults="portalDefaults" choice />
        </Card>

        <div class="mt-2 flex items-center gap-3 pt-4 shadow-[inset_0_1px_0_var(--rule)] [&>button:last-child]:ml-auto">
          <Link
            class="text-small tracking-[-0.01em] whitespace-nowrap text-ring no-underline hover:underline"
            href="/kontakte"
          >{{ $t('common.cancel') }}</Link>
          <Button variant="pill" type="submit" :disabled="form.processing">{{ $t('contacts.submit') }}</Button>
        </div>
      </form>

      <template #aside>
        <CreateSummary
          :title="$t('common.summary')"
          :rows="[
            { label: $t('contacts.name'), value: form.name },
            { label: $t('contacts.email'), value: form.email },
            { label: $t('contacts.phone'), value: form.phone },
            { label: $t('contacts.city'), value: [form.postcode, form.city].filter(Boolean).join(' ') },
          ]"
        />
      </template>
    </FormShell>
  </AdminLayout>
</template>
