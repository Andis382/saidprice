import { describe, expect, it } from 'vitest'
import { addDays, blankItem, columnOf, daysUntil, isWon, lineTotal, totalsOf } from '../quote'

describe('lineTotal', () => {
  it('multiplies quantity by the price in cents', () => {
    expect(lineTotal(1, 8500)).toBe(8500)
    expect(lineTotal(2, 800)).toBe(1600)
  })

  it('rounds half up once, like the server', () => {
    expect(lineTotal(1.5, 2333)).toBe(3500) // 3499.5
    expect(lineTotal(0.15, 30)).toBe(5) // 0.15 is not exact in binary; counted in hundredths
    expect(lineTotal(2.5, 999)).toBe(2498) // 2497.5
  })

  it('treats empty fields as zero while someone is typing', () => {
    expect(lineTotal(null, 500)).toBe(0)
    expect(lineTotal(3, undefined)).toBe(0)
  })
})

describe('totalsOf', () => {
  it('sums the rounded lines and splits parts from labour', () => {
    const totals = totalsOf([
      { qty: 1, unitPriceCents: 6900, kind: 'PARTS' },
      { qty: 2, unitPriceCents: 800, kind: 'PARTS' },
      { qty: 1, unitPriceCents: 6000, kind: 'LABOUR' },
      { qty: 1, unitPriceCents: 1000, kind: 'OTHER' },
    ])
    expect(totals).toEqual({ total: 15500, parts: 8500, labour: 6000, other: 1000 })
  })

  it('is zero for no lines', () => {
    expect(totalsOf([])).toEqual({ total: 0, parts: 0, labour: 0, other: 0 })
  })
})

describe('isWon', () => {
  it('counts accepted quotes unless the job fell through', () => {
    expect(isWon({ status: 'ACCEPTED', outcome: null })).toBe(true)
    expect(isWon({ status: 'ACCEPTED', outcome: 'LOST' })).toBe(false)
    expect(isWon({ status: 'SENT', outcome: 'WON' })).toBe(true)
    expect(isWon({ status: 'SILENT', outcome: null })).toBe(false)
  })
})

describe('board and dates', () => {
  it('puts expired quotes with the silent ones', () => {
    expect(columnOf('EXPIRED')).toBe('SILENT')
    expect(columnOf('VIEWED')).toBe('VIEWED')
  })

  it('adds calendar days to a plain date across months', () => {
    expect(addDays('2026-09-23', 14)).toBe('2026-10-07')
    expect(addDays('2026-12-25', 7)).toBe('2027-01-01')
  })

  it('counts whole days until a plain date', () => {
    const today = new Date(2026, 8, 23, 18, 30)
    expect(daysUntil('2026-09-23', today)).toBe(0)
    expect(daysUntil('2026-10-07', today)).toBe(14)
    expect(daysUntil('2026-09-20', today)).toBe(-3)
  })

  it('starts a new line at zero', () => {
    expect(blankItem('LABOUR', 'Labour')).toEqual({ description: 'Labour', qty: 1, unit: null, unitPriceCents: 0, kind: 'LABOUR' })
  })
})
