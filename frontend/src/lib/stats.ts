/** How long customers take to answer, in the unit a person would use: 40 minutes, 5 hours, 2.5 days. */
export function answerTime(hours: number | null | undefined): { unit: 'minutes' | 'hours' | 'days'; n: number } | null {
  if (hours === null || hours === undefined) return null
  if (hours < 1) return { unit: 'minutes', n: Math.max(1, Math.round(hours * 60)) }
  if (hours < 36) return { unit: 'hours', n: Math.round(hours) }
  return { unit: 'days', n: Math.round((hours / 24) * 10) / 10 }
}

/** Share of `part` in `whole` as 0..1, or null when there is nothing to divide. */
export function share(part: number, whole: number): number | null {
  return whole > 0 ? part / whole : null
}
