import type { Meta, StoryObj } from '@storybook/vue3-vite'
import SendByMailView from './SendByMailView.vue'
import QuoteSendView from './quote/QuoteSendView.vue'
import { leadQuote } from '../pages/__stories__/fixtures'

/*
 * Muster für jedes „Per E-Mail senden“: links Empfänger, Betreff, Nachricht,
 * rechts die E-Mail, wie sie ankommt. Pro Dokument ändern sich nur drei
 * Dinge: der Button in der Mail (cta), die Karte des Dokuments (#card) und
 * was mitgeschickt wird (#note). Galerie, Vertrag und Portalzugang sind
 * Entwürfe, gebaut ist bisher nur das Angebot.
 */
const meta = {
  title: 'Muster/Per E-Mail senden',
  component: SendByMailView,
  parameters: { layout: 'fullscreen' },
  args: {
    studioName: 'Studio Nordlicht',
    recipientName: 'Lena Brandt',
    recipientEmail: 'lena.brandt@web.de',
    subject: '',
    message: { blocks: [] },
    cta: '',
  },
} satisfies Meta<typeof SendByMailView>

export default meta
type Story = StoryObj<typeof meta>

const greeting = (text: string) => ({
  blocks: [
    { type: 'paragraph', data: { text: 'Hallo Lena,' } },
    { type: 'paragraph', data: { text } },
  ],
})

export const Angebot: Story = {
  render: (args) => ({
    components: { QuoteSendView },
    setup: () => ({ args, quote: leadQuote }),
    template: `<QuoteSendView :quote="quote" :studio-name="args.studioName" :recipient-name="args.recipientName" :recipient-email="args.recipientEmail" />`,
  }),
}

export const Galerie: Story = {
  args: {
    subject: 'Eure Galerie',
    message: greeting('eure Bilder aus Gut Basthorst sind da.'),
    cta: 'Galerie ansehen',
  },
  render: (args) => ({
    components: { SendByMailView },
    setup: () => ({ args }),
    template: `
      <SendByMailView v-bind="args">
        <template #card>
          <div class="flex items-baseline justify-between px-[18px] py-3.5">
            <span class="text-[13px] font-semibold">Hochzeit Brandt</span>
            <span class="text-[11.5px] text-faint">412 Bilder</span>
          </div>
        </template>
      </SendByMailView>
    `,
  }),
}

export const Vertrag: Story = {
  args: {
    subject: 'Euer Vertrag',
    message: greeting('anbei euer Vertrag zur Unterschrift.'),
    cta: 'Vertrag unterschreiben',
  },
  render: (args) => ({
    components: { SendByMailView },
    setup: () => ({ args }),
    template: `
      <SendByMailView v-bind="args">
        <template #note>{{ $t('sendMail.pdfNote') }}</template>
        <template #card>
          <div class="flex items-baseline justify-between px-[18px] py-3.5">
            <span class="text-[13px] font-semibold">Vertrag</span>
            <span class="text-[11.5px] text-faint">Hochzeitsreportage · 13.09.2026</span>
          </div>
        </template>
      </SendByMailView>
    `,
  }),
}

/** Ohne Dokument keine Karte: die Mail trägt nur Nachricht und Button. */
export const Portalzugang: Story = {
  args: {
    subject: 'Euer Kundenbereich',
    message: greeting('hier ist euer Zugang zum Kundenbereich.'),
    cta: 'Kundenbereich öffnen',
  },
}
