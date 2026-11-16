// @vitest-environment happy-dom
import { describe, expect, it, vi } from 'vitest'
import { createMergeFieldBlock } from './mergeFieldTool'

describe('createMergeFieldBlock', () => {
  const Block = createMergeFieldBlock([{ path: 'contact.name', sample: 'Lena Meier' }])

  it('offers one inserter entry per field', () => {
    expect(Block.toolbox.map((entry) => entry.data.path)).toEqual(['contact.name'])
  })

  it('replaces itself with a paragraph holding the chip', () => {
    const api = {
      blocks: { getBlockIndex: vi.fn(() => 2), insert: vi.fn() },
      caret: { setToBlock: vi.fn(() => true) },
    }

    new Block({ data: { path: 'contact.name' }, api, block: { id: 'b1' } }).rendered()

    expect(api.blocks.insert).toHaveBeenCalledWith(
      'paragraph',
      { text: '<span class="doc-mf" data-mf="contact.name" contenteditable="false">Lena Meier</span>&nbsp;' },
      undefined,
      2,
      true,
      true,
    )
    expect(api.caret.setToBlock).toHaveBeenCalledWith(2, 'end')
  })
})
