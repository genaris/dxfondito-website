import { apiGet, apiSend } from './api.ts'
import type { Activity } from './activities.ts'

export type MailKind = 'qsl' | 'certificate'

/** The address for the messages of a call sign (FR-MAIL-4). */
export interface Recipient {
  email: string | null
  /** "book": the address book. "adif": the most recent log. */
  source: 'book' | 'adif' | null
  /** The other addresses that the logs gave. */
  others: string[]
  /** The address of the last message, if it is different now. */
  changedFrom: string | null
  noMail: boolean
}

/** A message or a manual mark of the record (FR-MAIL-7, FR-MAIL-14). */
export interface DeliveryInfo {
  method: 'email' | 'manual'
  status: 'sent' | 'failed'
  recipient: string | null
  certificateDate: string | null
  error: string | null
  at: string
}

export interface MessageText {
  subject: string
  body: string
  /** True for an own text of the activity or the season, or a changed general text. */
  own: boolean
}

export interface Usage {
  limit: number
  used: number
}

export interface QslParticipant {
  callSign: string
  name: string
  recipient: Recipient
  contacts: number
  /** The contacts with a QSL card: their operator has a template. */
  cards: number
  /** The QSL cards that no message had. */
  newCards: number
  status: 'pending' | 'sent' | 'new'
  last: DeliveryInfo | null
}

export interface ActivityMail {
  activity: Activity
  template: MessageText
  variables: Record<string, string>
  configured: boolean
  usage: Usage | null
  retryAt: string | null
  participants: QslParticipant[]
}

export interface CertificateItem {
  callSign: string
  points: number
  level: string
  /** YYYY-MM-DD, or null if the participant does not have the certificate now. */
  date: string | null
  available: boolean
  name: string
  recipient: Recipient
  status: 'pending' | 'sent' | 'changed' | 'revoked' | 'unavailable'
  last: DeliveryInfo | null
}

export interface CertificateMail {
  season: number
  template: MessageText
  variables: Record<string, string>
  configured: boolean
  usage: Usage | null
  retryAt: string | null
  certificates: CertificateItem[]
}

export interface Preview {
  to: string | null
  subject: string
  text: string
  attachments: string[]
  unknown: string[]
}

export interface SendResult {
  results: { callSign?: string; key?: string; status: 'sent' | 'failed' | 'skipped'; reason?: string; error?: string | null }[]
  retryAt: string | null
}

export interface SeasonMail {
  season: number
  activities: (Activity & { participants: number; sent: number; pending: number })[]
  certificates: { pending: number; changed: number }
}

export interface AddressBookRow {
  callSign: string
  name: string | null
  email: string | null
  noMail: boolean
  notes: string | null
  updatedAt: string | null
  recipient: Recipient
  emailInvalid: boolean
}

export interface ContactCard {
  callSign: string
  entry: Omit<AddressBookRow, 'recipient' | 'emailInvalid'> | null
  officialName: string | null
  known: { email: string; lastQsoAt: string; invalid: boolean }[]
  recipient: Recipient
}

export type CertificateKey = { callSign: string; points: number }

// The address book.

export function readAddressBook(): Promise<AddressBookRow[]> {
  return apiGet<AddressBookRow[]>('/address-book')
}

export function readContact(callSign: string): Promise<ContactCard> {
  return apiGet<ContactCard>(`/address-book/${callSign}`)
}

export function saveContact(
  callSign: string,
  data: { name: string; email: string; noMail: boolean; notes: string },
): Promise<ContactCard> {
  return apiSend<ContactCard>('PUT', `/address-book/${callSign}`, data)
}

export function deleteContact(callSign: string): Promise<unknown> {
  return apiSend('DELETE', `/address-book/${callSign}`)
}

export function setInvalidEmail(email: string, invalid: boolean): Promise<unknown> {
  return apiSend('PUT', '/invalid-emails', { email, invalid })
}

// The texts of the messages.

export function readTemplate(kind: MailKind): Promise<MessageText & { variables: Record<string, string> }> {
  return apiGet(`/mail-templates/${kind}`)
}

export function saveTemplate(kind: MailKind, subject: string, body: string): Promise<MessageText> {
  return apiSend<MessageText>('PUT', `/mail-templates/${kind}`, { subject, body })
}

export function resetTemplate(kind: MailKind): Promise<MessageText> {
  return apiSend<MessageText>('DELETE', `/mail-templates/${kind}`)
}

// The overview.

export function readSeasonMail(season: number): Promise<SeasonMail> {
  return apiGet<SeasonMail>(`/seasons/${season}/mail`)
}

export function readMailSummary(): Promise<{ certificates: { pending: number; changed: number } }> {
  return apiGet('/mail/summary')
}

// The QSL messages of an activity.

export function readActivityMail(activityId: number): Promise<ActivityMail> {
  return apiGet<ActivityMail>(`/activities/${activityId}/mail`)
}

export function saveActivityTemplate(activityId: number, subject: string, body: string): Promise<ActivityMail> {
  return apiSend<ActivityMail>('PUT', `/activities/${activityId}/mail-template`, { subject, body })
}

export function resetActivityTemplate(activityId: number): Promise<ActivityMail> {
  return apiSend<ActivityMail>('DELETE', `/activities/${activityId}/mail-template`)
}

export function previewQsl(activityId: number, callSign: string, subject: string, body: string): Promise<Preview> {
  return apiSend<Preview>('POST', `/activities/${activityId}/mail/preview`, { callSign, subject, body })
}

export function testQsl(activityId: number, callSign: string, subject: string, body: string): Promise<{ to: string }> {
  return apiSend('POST', `/activities/${activityId}/mail/test`, { callSign, subject, body })
}

export function sendQsl(activityId: number, callSigns: string[], resend: boolean): Promise<SendResult> {
  return apiSend<SendResult>('POST', `/activities/${activityId}/mail/send`, { callSigns, resend })
}

export function markQsl(activityId: number, callSigns: string[]): Promise<{ marked: number }> {
  return apiSend('POST', `/activities/${activityId}/mail/mark`, { callSigns })
}

export function unmarkQsl(activityId: number, callSigns: string[]): Promise<{ unmarked: number }> {
  return apiSend('POST', `/activities/${activityId}/mail/unmark`, { callSigns })
}

// The certificate messages of a season.

export function readCertificateMail(season: number): Promise<CertificateMail> {
  return apiGet<CertificateMail>(`/seasons/${season}/certificate-mail`)
}

export function saveCertificateMailTemplate(season: number, subject: string, body: string): Promise<CertificateMail> {
  return apiSend<CertificateMail>('PUT', `/seasons/${season}/certificate-mail-template`, { subject, body })
}

export function resetCertificateMailTemplate(season: number): Promise<CertificateMail> {
  return apiSend<CertificateMail>('DELETE', `/seasons/${season}/certificate-mail-template`)
}

export function previewCertificate(season: number, key: CertificateKey, subject: string, body: string): Promise<Preview> {
  return apiSend<Preview>('POST', `/seasons/${season}/certificate-mail/preview`, { ...key, subject, body })
}

export function testCertificate(season: number, key: CertificateKey, subject: string, body: string): Promise<{ to: string }> {
  return apiSend('POST', `/seasons/${season}/certificate-mail/test`, { ...key, subject, body })
}

export function sendCertificates(season: number, certificates: CertificateKey[], again: boolean): Promise<SendResult> {
  return apiSend<SendResult>('POST', `/seasons/${season}/certificate-mail/send`, { certificates, again })
}

export function markCertificates(season: number, certificates: CertificateKey[]): Promise<{ marked: number }> {
  return apiSend('POST', `/seasons/${season}/certificate-mail/mark`, { certificates })
}

export function unmarkCertificates(season: number, certificates: CertificateKey[]): Promise<{ unmarked: number }> {
  return apiSend('POST', `/seasons/${season}/certificate-mail/unmark`, { certificates })
}

/** The unknown variables of a text, such as "nombr" in "{nombr}". The API refuses them. */
export function unknownVariables(text: string, variables: Record<string, string>): string[] {
  const names = [...text.matchAll(/\{([^{}\s]*)\}/gu)].map((match) => match[1])
  return [...new Set(names.filter((name) => !(name in variables)))]
}

/** The text with a variable at the cursor of a text field. */
export function insertAt(text: string, start: number, end: number, insert: string): { text: string; cursor: number } {
  return { text: text.slice(0, start) + insert + text.slice(end), cursor: start + insert.length }
}

/** The number of items of one request. The API sends 10 at most. A small batch shows the progress often. */
export const BATCH = 5

/**
 * Sends the items in batches, and waits at the limit of the hour (FR-MAIL-9). The callbacks show the progress.
 * The stop function ends the loop after the current batch.
 */
export async function sendInBatches<T>(
  items: T[],
  send: (batch: T[]) => Promise<SendResult>,
  callbacks: {
    onResult: (result: SendResult['results'][number]) => void
    onWait: (retryAt: string) => void
    stopped: () => boolean
    wait?: (milliseconds: number) => Promise<void>
    now?: () => number
  },
): Promise<void> {
  const wait = callbacks.wait ?? ((milliseconds: number) => new Promise<void>((resolve) => setTimeout(resolve, milliseconds)))
  const now = callbacks.now ?? (() => Date.now())
  let rest = [...items]
  while (rest.length > 0 && !callbacks.stopped()) {
    const batch = rest.slice(0, BATCH)
    const result = await send(batch)
    result.results.forEach(callbacks.onResult)
    // An answer without results and without a wait cannot advance: the loop stops.
    if (result.results.length === 0 && result.retryAt === null) break
    // At the limit, the API stops before an item: the items without a result go again.
    rest = rest.slice(result.results.length)
    if (result.retryAt !== null) {
      callbacks.onWait(result.retryAt)
      while (!callbacks.stopped() && Date.parse(result.retryAt) > now()) {
        await wait(Math.min(15000, Math.max(1000, Date.parse(result.retryAt) - now())))
      }
    }
  }
}

/** The text of the reason of a skipped item. */
export function skipReason(reason: string | undefined): string {
  switch (reason) {
    case 'no-email':
      return 'sin e-mail'
    case 'no-mail':
      return 'pidió no recibir correos'
    case 'no-qsl':
      return 'sin QSL (el operador no tiene plantilla)'
    case 'already-sent':
      return 'ya enviado'
    case 'not-reached':
      return 'no tiene el certificado'
    case 'no-template':
      return 'el nivel no tiene plantilla'
    default:
      return 'omitido'
  }
}

/** True for a call sign without a usable address: no address, or the mark "no messages". */
export function withoutEmail(recipient: Recipient): boolean {
  return recipient.email === null || recipient.noMail
}
