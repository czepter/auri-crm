import { describe, expect, it } from 'vitest'
import { steps, unanswered } from './questionnaire'

const field = (over: Record<string, unknown>) => ({ id: 'a', type: 'string', required: true, files: [], ...over }) as never

describe('questionnaire steps', () => {
  it('chunks fields into steps of four', () => {
    const fields = Array.from({ length: 9 }, (_, i) => field({ id: String(i) }))

    expect(steps(fields).map((s) => s.length)).toEqual([4, 4, 1])
  })

  it('opens a step per section without counting it as a question', () => {
    const fields = [field({ type: 'section' }), field({}), field({}), field({ type: 'section' }), field({})]

    expect(steps(fields).map((s) => s.length)).toEqual([3, 2])
  })

  it('flags a required field without an answer', () => {
    expect(unanswered(field({}), { a: '  ' })).toBe(true)
    expect(unanswered(field({}), { a: 'x' })).toBe(false)
    expect(unanswered(field({ required: false }), {})).toBe(false)
    expect(unanswered(field({ type: 'file' }), {})).toBe(true)
    expect(unanswered(field({ type: 'boolean' }), {})).toBe(false)
    expect(unanswered(field({ type: 'section' }), {})).toBe(false)
  })
})
