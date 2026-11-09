import { useState } from 'react'
import type { ReactNode } from 'react'
import { adifUrl, normalizeSuffix } from '../live.ts'

/**
 * The links to the ADIF file of each operator (FR-LIVE-13). In an EFE activity the stations have no suffix: the user
 * writes one for an activity away from home. The suffix goes only in the file.
 */
export function AdifLinks({
  activityId,
  series,
  operators,
  children,
}: {
  activityId: number
  series: string
  operators: { id: number; label: string }[]
  children?: ReactNode
}) {
  const [suffix, setSuffix] = useState('')
  return (
    <>
      {series === 'EFE' && (
        <div className="form">
          <label>
            Sufijo, si se operó fuera del QTH
            <input value={suffix} placeholder="D" onChange={(event) => setSuffix(normalizeSuffix(event.target.value))} />
          </label>
          <p className="hint">Se agrega a la distintiva sólo en el ADIF que se baja. Por ejemplo, con D queda LU2AOG/D.</p>
        </div>
      )}
      <p className="actions">
        {children}
        {operators.map((operator) => (
          <a key={operator.id} className="chip" href={adifUrl(activityId, operator.id, suffix)}>
            {suffix ? `${operator.label}/${suffix}` : operator.label}
          </a>
        ))}
      </p>
    </>
  )
}
