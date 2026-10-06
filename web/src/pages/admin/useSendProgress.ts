import { useRef, useState } from 'react'
import type { SendResult } from '../../mail.ts'

type ResultLine = SendResult['results'][number] & { key: string }

export interface SendProgressState {
  running: boolean
  total: number
  sent: number
  failed: number
  skipped: number
  waitingUntil: string | null
  log: ResultLine[]
  stop: () => void
  run: (
    total: number,
    work: (callbacks: {
      onResult: (result: SendResult['results'][number]) => void
      onWait: (retryAt: string) => void
      stopped: () => boolean
    }) => Promise<void>,
  ) => Promise<void>
}

/**
 * The progress of a sending in batches (FR-MAIL-9): the counts, the wait at the limit of the hour, and the problems.
 */
export function useSendProgress(): SendProgressState {
  const [running, setRunning] = useState(false)
  const [total, setTotal] = useState(0)
  const [sent, setSent] = useState(0)
  const [failed, setFailed] = useState(0)
  const [skipped, setSkipped] = useState(0)
  const [waitingUntil, setWaitingUntil] = useState<string | null>(null)
  const [log, setLog] = useState<ResultLine[]>([])
  const stopped = useRef(false)

  async function run(count: number, work: Parameters<SendProgressState['run']>[1]) {
    stopped.current = false
    setRunning(true)
    setTotal(count)
    setSent(0)
    setFailed(0)
    setSkipped(0)
    setWaitingUntil(null)
    setLog([])
    try {
      await work({
        onResult: (result) => {
          setWaitingUntil(null)
          if (result.status === 'sent') setSent((value) => value + 1)
          else {
            if (result.status === 'failed') setFailed((value) => value + 1)
            else setSkipped((value) => value + 1)
            setLog((lines) => [...lines, { ...result, key: `${lines.length}` }])
          }
        },
        onWait: setWaitingUntil,
        stopped: () => stopped.current,
      })
    } catch {
      setLog((lines) => [...lines, { status: 'failed', error: 'error de conexión con el sitio', key: `${lines.length}` }])
    } finally {
      setRunning(false)
      setWaitingUntil(null)
    }
  }

  return { running, total, sent, failed, skipped, waitingUntil, log, stop: () => (stopped.current = true), run }
}
