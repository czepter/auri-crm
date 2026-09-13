import type { Meta, StoryObj } from '@storybook/vue3-vite'
import { Table, TableCell, TableGroupRow, TableHead, TableRow } from '.'

/*
 * Die Liste des Admins: Kopfzeile, Zeilen, und Gruppen als Zeile im Körper
 * statt als zweite Tabelle. `align="right"` schaltet die Tabellenziffern mit —
 * rechts steht hier nur, was sich untereinander ausrichten muss.
 */
const meta = {
  title: 'Struktur/Table',
  component: Table,
  parameters: { layout: 'fullscreen' },
  render: () => ({
    components: { Table, TableCell, TableGroupRow, TableHead, TableRow },
    setup: () => ({
      zeilen: [
        { id: 1, name: 'Hochzeit Rahn', datum: '13.09.2026', ort: 'Gut Basthorst', kontakt: 'Jana Rahn', summe: '4.260 €' },
        { id: 2, name: 'Hochzeit Wendt', datum: '04.07.2026', ort: 'Hof Sierksdorf', kontakt: 'Lena Wendt', summe: '3.180 €' },
      ],
    }),
    template: `
      <div style="background: var(--paper); padding: 24px 0;">
        <Table>
          <thead>
            <tr>
              <TableHead>Auftrag</TableHead>
              <TableHead align="right">Termin</TableHead>
              <TableHead>Ort</TableHead>
              <TableHead>Kontakt</TableHead>
              <TableHead align="right">Summe</TableHead>
            </tr>
          </thead>
          <tbody>
            <TableGroupRow :colspan="5">Gebucht <span style="font-weight: 400;">2</span></TableGroupRow>
            <TableRow v-for="zeile in zeilen" :key="zeile.id">
              <TableCell tone="name">{{ zeile.name }}</TableCell>
              <TableCell align="right">{{ zeile.datum }}</TableCell>
              <TableCell tone="muted">{{ zeile.ort }}</TableCell>
              <TableCell tone="muted">{{ zeile.kontakt }}</TableCell>
              <TableCell align="right">{{ zeile.summe }}</TableCell>
            </TableRow>
          </tbody>
        </Table>
      </div>
    `,
  }),
} satisfies Meta<typeof Table>

export default meta
type Story = StoryObj<typeof meta>

/** Die Projektliste, wie sie unter /projekte steht. */
export const Projektliste: Story = {}

/** Ohne Gruppen: eine Liste, die nur eine Ordnung kennt. */
export const OhneGruppen: Story = {
  render: () => ({
    components: { Table, TableCell, TableHead, TableRow },
    setup: () => ({
      zeilen: [
        { id: 1, name: 'Jana Rahn', rolle: 'Braut', mail: 'jana@rahn.de' },
        { id: 2, name: 'Michael Rahn', rolle: 'Bräutigam', mail: 'michael@rahn.de' },
      ],
    }),
    template: `
      <div style="background: var(--paper); padding: 24px 0;">
        <Table>
          <thead>
            <tr>
              <TableHead>Name</TableHead>
              <TableHead>Rolle</TableHead>
              <TableHead>E-Mail</TableHead>
            </tr>
          </thead>
          <tbody>
            <TableRow v-for="zeile in zeilen" :key="zeile.id">
              <TableCell tone="name">{{ zeile.name }}</TableCell>
              <TableCell tone="muted">{{ zeile.rolle }}</TableCell>
              <TableCell tone="muted">{{ zeile.mail }}</TableCell>
            </TableRow>
          </tbody>
        </Table>
      </div>
    `,
  }),
}
