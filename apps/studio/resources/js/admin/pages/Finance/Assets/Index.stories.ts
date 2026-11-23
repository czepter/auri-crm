import type { Meta, StoryObj } from '@storybook/vue3-vite'
import Index from './Index.vue'

const page = {
  auth: {
    user: { id: 'u1', name: 'Christian Zepter', email: 'christian@atelier-nordlicht.de' },
    can: ['manage_finances'] as App.Enums.Capability[],
  },
}

const meta = {
  title: 'Bildschirme/Finanzen/Anlagen',
  component: Index,
  parameters: { layout: 'fullscreen', url: '/finanzen/anlagen', page },
  args: {
    year: 2026,
    years: [2026, 2025],
    locked: false,
    rows: [
      { id: 'a1', inventoryNo: 'INV-26-001', name: 'Mobiler Rechner', acquiredAt: '2026-04-10', method: 'linear', usefulLifeMonths: 12, cost: 145_000, bookValue: 36_250, businessShare: 100, disposedAt: null, disposalProceeds: null, expenseId: 'e15' },
      { id: 'a2', inventoryNo: 'INV-26-002', name: 'Objektiv 85 mm', acquiredAt: '2026-05-03', method: 'gwg', usefulLifeMonths: null, cost: 64_000, bookValue: 0, businessShare: 100, disposedAt: null, disposalProceeds: null, expenseId: 'e16' },
      { id: 'a3', inventoryNo: 'INV-25-001', name: 'Kamera-Body', acquiredAt: '2025-09-08', method: 'linear', usefulLifeMonths: 36, cost: 285_600, bookValue: 158_667, businessShare: 100, disposedAt: null, disposalProceeds: null, expenseId: 'y1' },
      { id: 'a4', inventoryNo: 'INV-25-002', name: 'Blitzanlage', acquiredAt: '2025-03-14', method: 'linear', usefulLifeMonths: 36, cost: 107_100, bookValue: 0, businessShare: 100, disposedAt: '2026-06-30', disposalProceeds: 30_000, expenseId: 'y2' },
    ],
  },
} satisfies Meta<typeof Index>

export default meta
type Story = StoryObj<typeof meta>

export const Liste: Story = {}

export const Gesperrt: Story = {
  args: { year: 2025, locked: true, rows: [] },
}

export const Leer: Story = { args: { year: 2023, rows: [] } }
