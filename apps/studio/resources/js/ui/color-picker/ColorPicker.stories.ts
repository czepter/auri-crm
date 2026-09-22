import type { Meta, StoryObj } from '@storybook/vue3-vite'
import { ref } from 'vue'
import { ColorPicker } from '.'

const meta = {
  title: 'Formular/ColorPicker',
  component: ColorPicker,
  parameters: { layout: 'centered' },
} satisfies Meta<typeof ColorPicker>

export default meta
type Story = StoryObj<typeof meta>

export const Standard: Story = {
  render: () => ({
    components: { ColorPicker },
    setup: () => ({ color: ref('#1C6870') }),
    template: '<div class="w-[260px]"><ColorPicker v-model="color" placeholder="#1C6870" aria-label="Farbe" /></div>',
  }),
}
