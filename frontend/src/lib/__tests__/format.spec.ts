import { afterEach, describe, expect, it } from 'vitest'
import { formatAge, formatMoney, formatPhone, initials, parseDecimal, parseMoney, centsToInput, setFormatLocale } from '../format'

describe('format helpers', () => {
  afterEach(() => setFormatLocale('en'))

  it('groups Albanian mobile numbers', () => {
    expect(formatPhone('355691234567')).toBe('+355 69 123 4567')
  })

  it('reads money the way people type it', () => {
    expect(parseMoney('12,50')).toBe(1250)
    expect(parseMoney('12.5')).toBe(1250)
    expect(parseMoney('')).toBeNull()
    expect(parseMoney('abc')).toBeNull()
  })

  it('reads decimals with a comma or a dot', () => {
    expect(parseDecimal('1,5')).toBe(1.5)
    expect(parseDecimal(' 2 ')).toBe(2)
    expect(parseDecimal('')).toBeNull()
    expect(parseDecimal('x')).toBeNull()
  })

  it('round-trips cents to an input value', () => {
    expect(centsToInput(1250)).toBe('12.50')
    expect(centsToInput(1200)).toBe('12')
  })

  it('formats money for the reader, not only the screen', () => {
    expect(formatMoney(14500)).toBe('€145')
    expect(formatMoney(14500, { locale: 'sq' }).replace(/\s/g, ' ')).toBe('145 €')
  })

  it('makes initials', () => {
    expect(initials('Arben Hoxha')).toBe('AH')
    expect(initials('Mira')).toBe('M')
  })

  it('says how long ago in as few characters as a card allows', () => {
    const now = new Date('2026-09-23T12:00:00Z')
    expect(formatAge('2026-09-23T11:59:40Z', now)).toBe('now')
    expect(formatAge('2026-09-23T11:15:00Z', now)).toBe('45m')
    expect(formatAge('2026-09-23T02:00:00Z', now)).toBe('10h')
    expect(formatAge('2026-09-21T12:00:00Z', now)).toBe('2d')
    expect(formatAge('2026-09-02T12:00:00Z', now)).toBe('3w')
    expect(formatAge('2026-05-23T12:00:00Z', now)).toBe('4mo')
    setFormatLocale('sq')
    expect(formatAge('2026-09-21T12:00:00Z', now)).toBe('2 ditë')
    expect(formatAge('2026-09-23T02:00:00Z', now)).toBe('10 orë')
  })
})
