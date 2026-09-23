import { describe, expect, it } from 'vitest'
import { extensionFor, formatDuration, levelFromSamples, pickMimeType, pushLevel } from '../audio'

describe('levelFromSamples', () => {
  it('is zero for silence and for an empty frame', () => {
    expect(levelFromSamples(new Uint8Array(512).fill(128))).toBe(0)
    expect(levelFromSamples([])).toBe(0)
  })

  it('reaches the top for a full-scale signal and stays within 0..1', () => {
    const loud = Uint8Array.from({ length: 512 }, (_, i) => (i % 2 ? 255 : 0))
    expect(levelFromSamples(loud)).toBe(1)
  })

  it('lifts quiet speech so the meter visibly moves', () => {
    const quiet = Uint8Array.from({ length: 512 }, (_, i) => (i % 2 ? 136 : 120)) // about 6% amplitude
    const level = levelFromSamples(quiet)
    expect(level).toBeGreaterThan(0.3)
    expect(level).toBeLessThan(0.5)
  })
})

describe('pushLevel', () => {
  it('keeps the newest levels, oldest dropping off the left', () => {
    expect(pushLevel([0.1, 0.2, 0.3], 0.9, 3)).toEqual([0.2, 0.3, 0.9])
    expect(pushLevel([0.1], 0.5, 3)).toEqual([0.1, 0.5])
  })
})

describe('formatDuration', () => {
  it('shows minutes and two-digit seconds', () => {
    expect(formatDuration(0)).toBe('0:00')
    expect(formatDuration(7.9)).toBe('0:07')
    expect(formatDuration(65)).toBe('1:05')
    expect(formatDuration(-3)).toBe('0:00')
  })
})

describe('recording format', () => {
  it('prefers Opus in WebM, then MP4 for Safari', () => {
    expect(pickMimeType((t) => t.startsWith('audio/webm'))).toBe('audio/webm;codecs=opus')
    expect(pickMimeType((t) => t === 'audio/mp4')).toBe('audio/mp4')
    expect(pickMimeType(() => false)).toBe('')
  })

  it('names the file after the container', () => {
    expect(extensionFor('audio/webm;codecs=opus')).toBe('webm')
    expect(extensionFor('audio/mp4')).toBe('m4a')
    expect(extensionFor('audio/ogg;codecs=opus')).toBe('ogg')
  })
})
