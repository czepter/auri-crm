import type { Meta, StoryObj } from '@storybook/vue3-vite'
import Edit from './Edit.vue'

const QUEUE: App.Crm.Data.ContactRowData[] = [
  { id: 'c1', name: 'Lena Brandt', email: 'lena.brandt@posteo.de', phone: '0176 2244881', projectCount: 2, standing: 'running', nextDate: '2027-06-11', latestProject: 'Hochzeit Brandt · Gut Basthorst' },
  { id: 'c2', name: 'Tom Brandt', email: 'tom@brandt-tischlerei.de', phone: null, projectCount: 2, standing: 'running', nextDate: '2027-06-11', latestProject: 'Hochzeit Brandt · Gut Basthorst' },
  { id: 'c3', name: 'Kerstin Voß', email: 'k.voss@nordwerk.de', phone: '040 559012', projectCount: 1, standing: 'past', nextDate: null, latestProject: 'Businessporträts Nordwerk' },
]

const PORTAL_DEFAULTS: Record<string, string | boolean | null> = {
  portal_visible: true,
  accept_quotes: true,
  sign_contracts: true,
  payment_status: false,
  gallery_downloads: true,
  gallery_lists: true,
  gallery_comments: false,
  messaging_mode: 'booking',
}

const meta = {
  title: 'Bildschirme/Kontakte/Kontakt',
  component: Edit,
  parameters: { layout: 'fullscreen', url: '/kontakte/c1' },
  args: {
    contacts: QUEUE,
    contact: { id: 'c1', name: 'Lena Brandt', email: 'lena.brandt@posteo.de', phone: '0176 2244881', projectCount: 2 },
    billingAddress: { street: 'Eppendorfer Weg 214', postcode: '20251', city: 'Hamburg' },
    correspondence: [
      { id: 'm1', author: 'Lena Brandt', fromStaff: false, body: 'Den Termin am 11.06. halten wir. Können wir den Zweitfotografen noch dazunehmen?', sentAt: '2026-08-28T09:12:00+02:00', deliveryState: null, projectName: 'Hochzeit Brandt', projectId: 'p1', scheduledFor: null },
      { id: 'm2', author: 'Christian Zepter', fromStaff: true, body: 'Ja, ich lege den Zweitfotografen ins Angebot und schicke es heute.', sentAt: '2026-08-28T11:40:00+02:00', deliveryState: 'delivered', projectName: 'Hochzeit Brandt', projectId: 'p1', scheduledFor: null },
    ],
    projects: [
      { id: 'p1', name: 'Hochzeit Brandt · Gut Basthorst', role: 'primary', date: '2027-06-11', value: 426000, booked: true },
      { id: 'p2', name: 'Standesamt Hamburg', role: 'primary', date: '2027-06-11', value: 348000, booked: false },
    ],
    galleries: [
      { id: 'g1', title: 'Verlobungsshooting', expiresAt: '2027-01-31', assetCount: 84, openedOn: '2026-08-30' },
    ],
    connected: [{ id: 'c2', name: 'Tom Brandt', role: 'partner' }],
    portalAccessRevokedAt: null,
    canRevokePortalAccess: true,
    dsgvoErasedAt: null,
    canManageDsgvo: true,
    dsgvoRequests: [],
    suggestions: [
      {
        id: 's1',
        package: 'pk-album',
        name: 'Fotoalbum',
        note: 'Aus eurer Hochzeitsgalerie würde ich ein Album machen, solange die Reihenfolge des Tages noch sitzt.',
        author: 'Christian',
        suggestedOn: '2026-09-04',
        live: true,
      },
    ],
    offerablePackages: [
      { id: 'pk-album', name: 'Fotoalbum' },
      { id: 'pk-jahrestag', name: 'Paarshooting zum Jahrestag' },
    ],
    portalDefaults: PORTAL_DEFAULTS,
    portalOverrides: { gallery_comments: true },
    canPreviewPortal: true,
  },
} satisfies Meta<typeof Edit>

export default meta
type Story = StoryObj<typeof meta>

/** Ein Kontakt mit allem, was das Studio über ihn führt: Adresse, zwei Aufträge, Galerie, Verknüpfung. */
export const Vollstaendig: Story = {}

/** Frisch angelegt: nur ein Name, sonst nichts — kein Auftrag, keine Nachricht, keine Abweichung vom Standard. */
export const NurName: Story = {
  args: {
    contact: { id: 'c3', name: 'Kerstin Voß', email: null, phone: null, projectCount: 0 },
    billingAddress: { street: null, postcode: null, city: null },
    correspondence: [],
    projects: [],
    galleries: [],
    connected: [],
    portalOverrides: {},
  },
}

/** Portalzugang widerrufen, DSGVO-Auskunft angefragt, Daten gelöscht. */
export const ZugangWiderrufen: Story = {
  args: {
    portalAccessRevokedAt: '2026-09-02T08:00:00+02:00',
    dsgvoErasedAt: '2026-09-05T10:30:00+02:00',
    dsgvoRequests: [{ id: 'd1', kind: 'export', requestedAt: '2026-09-01T16:20:00+02:00' }],
  },
}
