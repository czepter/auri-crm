import type { Meta, StoryObj } from '@storybook/vue3-vite'
import SendByMailView from './SendByMailView.vue'
import angebot from './__stories__/mail/angebot.html?raw'
import galerie from './__stories__/mail/galerie.html?raw'
import vertrag from './__stories__/mail/vertrag.html?raw'
import portalzugang from './__stories__/mail/portalzugang.html?raw'

/*
 * Muster für jedes „Per E-Mail senden“: links Empfänger, Betreff, Nachricht,
 * rechts die Mail, wie der Server sie rendert (MailPreview::html). Was eine
 * Mail über die Nachricht hinaus schreibt, gehört in ihre Notification-Klasse,
 * nicht in diese Ansicht. Die HTML-Dateien sind Abzüge solcher Renderings mit
 * Beispieldaten; nach einer Änderung an resources/mail neu erzeugen.
 */
const meta = {
  title: 'Muster/Per E-Mail senden',
  component: SendByMailView,
  parameters: { layout: 'fullscreen' },
  args: {
    recipients: [{ name: 'Lena Brandt', email: 'lena.brandt@web.de' }],
    subject: 'Angebot',
    message: greeting('vielen Dank für euer Vertrauen — hier euer Angebot.'),
    mailHtml: angebot,
  },
} satisfies Meta<typeof SendByMailView>

export default meta
type Story = StoryObj<typeof meta>

function greeting(text: string) {
  return {
    blocks: [
      { type: 'paragraph', data: { text: 'Hallo Lena,' } },
      { type: 'paragraph', data: { text } },
    ],
  }
}

export const Angebot: Story = {}

export const Galerie: Story = {
  args: { subject: 'Hochzeit Brandt', message: greeting('eure Bilder sind da.'), mailHtml: galerie },
}

export const Vertrag: Story = {
  args: {
    recipients: [
      { name: 'Lena Brandt', email: 'lena.brandt@web.de' },
      { name: 'Tom Brandt', email: 'tom.brandt@web.de' },
    ],
    subject: 'Vertrag',
    message: greeting('hier euer Vertrag zur Unterschrift.'),
    mailHtml: vertrag,
  },
}

export const Portalzugang: Story = {
  args: { subject: 'Portal-Zugang', message: greeting('hier euer Zugang zum Kundenbereich.'), mailHtml: portalzugang },
}
