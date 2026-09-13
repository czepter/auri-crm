import type { Meta, StoryObj } from '@storybook/vue3-vite'
import Rail from './Rail.vue'
import RailItem from './RailItem.vue'
import RailSection from './RailSection.vue'

/*
 * Die Spur neben dem Fall: wer dazugehört, was daran hängt, wann es ausläuft.
 * Ein RailItem mit `href` rendert hier, springt aber nicht — der Inertia-Router
 * läuft ohne Anwendung nicht, deshalb bleibt der Klick ohne Ziel.
 *
 * Unter 1080 px Fensterbreite ist die Spur leer, und das ist kein Fehler:
 * `max-[1080px]:hidden` blendet sie aus, weil man sie neben der Arbeit liest,
 * nicht statt ihrer. Für diese Stories das Fenster breit genug ziehen.
 */
const meta = {
  title: 'Struktur/Rail',
  component: Rail,
  parameters: { layout: 'fullscreen' },
  render: () => ({
    components: { Rail, RailItem, RailSection },
    setup: () => ({
      raten: [
        { faellig: '20.03.2026', betrag: '1.420 €' },
        { faellig: '20.06.2026', betrag: '1.420 €' },
        { faellig: '20.09.2026', betrag: '1.420 €' },
      ],
    }),
    template: `
      <div style="display: flex; height: 100vh; background: var(--paper);">
        <div style="flex: 1;"></div>
        <Rail>
          <RailSection title="Personen">
            <RailItem title="Jana Rahn" meta="Braut" />
            <RailItem title="Michael Rahn" meta="Bräutigam" />
          </RailSection>

          <RailSection title="Zahlungen">
            <RailItem
              v-for="rate in raten"
              :key="rate.faellig"
              :title="rate.betrag"
              :meta="'fällig ' + rate.faellig"
            />
          </RailSection>

          <RailSection title="Galerien">
            <RailItem title="Trauung" href="/galerien/1" meta="läuft ab am 13.03.2027" />
            <RailItem title="Feier Gut Basthorst" href="/galerien/2" meta="läuft ab am 13.03.2027" />
          </RailSection>
        </Rail>
      </div>
    `,
  }),
} satisfies Meta<typeof Rail>

export default meta
type Story = StoryObj<typeof meta>

/** Drei Gruppen nebeneinander — so steht die Spur an jedem Auftrag. */
export const Auftrag: Story = {}

/** Eine einzelne Gruppe: der Abstand zwischen zweien kommt von RailSection. */
export const EineGruppe: Story = {
  render: () => ({
    components: { Rail, RailItem, RailSection },
    template: `
      <div style="display: flex; height: 100vh; background: var(--paper);">
        <div style="flex: 1;"></div>
        <Rail>
          <RailSection title="Personen">
            <RailItem title="Jana Rahn" meta="Braut" />
            <RailItem title="Michael Rahn" meta="Bräutigam" />
          </RailSection>
        </Rail>
      </div>
    `,
  }),
}
