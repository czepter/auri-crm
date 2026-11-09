import { describe, expect, it } from 'vitest'
import { bandMetrics, cap, groupBy, isBare, laterStages, leadNextStep, splitNextStep, taskGroupLabel, taskGroups, timelineWindow } from './projectOverview'

const nums = [1, 2, 3, 4, 5, 6, 7]

describe('timelineWindow', () => {
  it('shows predecessor, current and the next two', () => {
    expect(timelineWindow(nums, 3)).toEqual([3, 4, 5, 6])
  })

  it('stays inside the list at both ends', () => {
    expect(timelineWindow(nums, 0)).toEqual([1, 2, 3, 4])
    expect(timelineWindow(nums, 6)).toEqual([4, 5, 6, 7])
  })

  it('ends on the last entry when none is current', () => {
    expect(timelineWindow(nums, -1)).toEqual([4, 5, 6, 7])
    expect(timelineWindow([1, 2], -1)).toEqual([1, 2])
  })
})

describe('cap', () => {
  it('cuts and reports the total', () => {
    expect(cap(nums, 3)).toEqual({ shown: [1, 2, 3], total: 7, capped: true })
    expect(cap([1], 3).capped).toBe(false)
  })
})

describe('splitNextStep', () => {
  const task = (id: string, isOverdue: boolean, isDone = false) => ({ id, isOverdue, isDone })

  it('takes the first overdue open task out of the list', () => {
    const { step, rest } = splitNextStep([task('a', false), task('b', true), task('c', true), task('d', false, true)])
    expect(step?.id).toBe('b')
    expect(rest.map((entry) => entry.id)).toEqual(['a', 'c'])
  })

  it('has no step without an overdue task', () => {
    expect(splitNextStep([task('a', false)]).step).toBeNull()
  })
})

describe('isBare', () => {
  it('is bare only when all four are empty', () => {
    expect(isBare({ shoots: 0, quote: false, tasks: 0, payments: 0 })).toBe(true)
    expect(isBare({ shoots: 0, quote: false, tasks: 1, payments: 0 })).toBe(false)
  })
})

describe('bandMetrics', () => {
  it('drops empty values and the band with fewer than two', () => {
    expect(bandMetrics([{ value: '1' }, { value: null }])).toEqual([])
    expect(bandMetrics([{ value: '1' }, { value: null }, { value: '2' }])).toEqual([{ value: '1' }, { value: '2' }])
  })
})

describe('groupBy', () => {
  it('keeps first-seen order', () => {
    expect(groupBy(['a1', 'b1', 'a2'], (s) => s[0])).toEqual([
      { key: 'a', items: ['a1', 'a2'] },
      { key: 'b', items: ['b1'] },
    ])
  })
})

describe('laterStages', () => {
  const stages = [{ value: 'a' }, { value: 'b' }, { value: 'c' }]

  it('lists what follows the current stage', () => {
    expect(laterStages(stages, 'a').map((stage) => stage.value)).toEqual(['b', 'c'])
    expect(laterStages(stages, 'c')).toEqual([])
  })

  it('offers nothing when the stage is unknown', () => {
    expect(laterStages(stages, null)).toEqual([])
  })
})

describe('taskGroups', () => {
  const row = (dueOn: string | null) => ({ dueOn })

  it('puts late first, months of this year, then years, undated last', () => {
    const groups = taskGroups(
      [row('2026-11-01'), row(null), row('2026-11-20'), row('2026-12-02'), row('2027-03-01'), row('2026-10-01')],
      '2026-11-08',
    )

    expect(groups.map((group) => group.key)).toEqual(['late', '2026-11', '2026-12', '2027', 'undated'])
    expect(groups[1].rows).toHaveLength(1)
  })

  it('names months and years', () => {
    const t = (key: string) => key
    expect(taskGroupLabel('2026-12', t)).toBe('Dezember 2026')
    expect(taskGroupLabel('2027', t)).toBe('2027')
    expect(taskGroupLabel('late', t)).toBe('project.tasksLate')
  })
})

describe('leadNextStep', () => {
  const task = { isOverdue: true, isDone: false }

  it('puts the overdue task before the quote draft', () => {
    expect(leadNextStep([task], { status: 'draft' })).toEqual({ kind: 'task', task })
  })

  it('offers the draft when nothing is overdue, and nothing for a sent quote', () => {
    expect(leadNextStep([], { status: 'draft' })).toEqual({ kind: 'quote' })
    expect(leadNextStep([], { status: 'sent' })).toBeNull()
    expect(leadNextStep([], null)).toBeNull()
  })
})
