import type { Meta, StoryObj } from '@storybook/vue3-vite'
import Galleries from './Galleries.vue'
import { header, shoots } from '../__stories__/fixtures'

const standesamt: App.Crm.Data.ShootData = {
    ...shoots[0],
    id: 's-rahn-standesamt',
    label: 'Standesamt',
    date: '2026-09-11',
    callTime: '10:30',
    endTime: '12:00',
    venue: 'Standesamt Trittau',
    venueName: 'Standesamt Trittau',
    venueCity: 'Trittau',
    isPrimary: false,
}

const galleries: App.Crm.Screens.ProjectGalleryData[] = [
    {
        id: 'g-rahn-tag',
        title: 'Hochzeitstag',
        shootId: 's-rahn',
        state: 'published',
        assetCount: 412,
        version: 2,
        expiresAt: '2027-03-13',
        inPortal: true,
    },
    {
        id: 'g-rahn-auswahl',
        title: 'Vorauswahl Fotobuch',
        shootId: null,
        state: 'draft',
        assetCount: 68,
        version: null,
        expiresAt: null,
        inPortal: false,
    },
]

const meta = {
    title: 'Bildschirme/Auftrag Galerien',
    component: Galleries,
    parameters: { layout: 'fullscreen', url: `/auftraege/${header.id}/galerien` },
    args: {
        header,
        galleries,
        shoots: [shoots[0], standesamt],
    },
} satisfies Meta<typeof Galleries>

export default meta
type Story = StoryObj<typeof meta>

/** Der Hochzeitstag ist freigegeben, die Vorauswahl noch Entwurf, das Standesamt hat keine Galerie. */
export const MitEntwurf: Story = {}

/** Keine Galerie angelegt: beide Shootings stehen unter den fehlenden. */
export const OhneGalerie: Story = {
    args: { galleries: [] },
}
