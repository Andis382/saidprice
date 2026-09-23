/** A WhatsApp message the app sent (or holds in the outbox when no account is connected). */
export type OutboundMessage = {
  id: number
  recipient: string
  recipientName: string | null
  templateKey: string
  body: string
  status: 'QUEUED' | 'SENT' | 'DELIVERED' | 'READ' | 'FAILED' | 'SIMULATED'
  error: string | null
  createdAt: string
  waMeUrl: string
}
