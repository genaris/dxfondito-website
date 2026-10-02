import { useCallback, useEffect, useState } from 'react'
import { readSeasons } from '../../activities.ts'
import {
  CERTIFICATE_KIND,
  certificateImageUrl,
  deleteCertificateTemplate,
  previewCertificate,
  readCertificateTemplates,
  saveCertificateTemplate,
} from '../../certificates.ts'
import type { CertificateTemplate, CertificateTemplates } from '../../certificates.ts'
import { changeError } from '../../messages.ts'
import { levelClass, levelName } from '../../program.ts'
import { SeasonSelect } from '../../SeasonSelect.tsx'
import { TemplateEditor } from '../TemplateEditor.tsx'

type ListState = { kind: 'loading' } | { kind: 'ready'; data: CertificateTemplates } | { kind: 'error' }
type Editing = { points: number; template: CertificateTemplate | null }

/**
 * The certificate templates: one for each level of each season (FR-CER-1, FR-CER-3, D-22).
 */
export function CertificatesPage() {
  const [list, setList] = useState<ListState>({ kind: 'loading' })
  const [seasons, setSeasons] = useState<number[]>([])
  const [season, setSeason] = useState<number | null>(null)
  const [editing, setEditing] = useState<Editing | null>(null)
  const [version, setVersion] = useState(0)
  const [notice, setNotice] = useState<string | null>(null)
  const [error, setError] = useState<string | null>(null)

  const load = useCallback(() => {
    readCertificateTemplates()
      .then((data) => setList({ kind: 'ready', data }))
      .catch(() => setList({ kind: 'error' }))
  }, [])

  useEffect(load, [load])

  useEffect(() => {
    readSeasons()
      .then(({ seasons: known, current }) => {
        // The next season too: the group can prepare its templates before the season starts.
        setSeasons([...new Set([current + 1, ...known])].sort((a, b) => b - a))
        setSeason(current)
      })
      .catch(() => setList({ kind: 'error' }))
  }, [])

  if (list.kind === 'error') return <p className="error">No se pudieron leer las plantillas de certificados.</p>
  if (list.kind === 'loading' || season === null) return <p>Cargando…</p>

  const { levels, templates } = list.data
  const options = [...new Set([...seasons, ...templates.map((template) => template.season)])].sort((a, b) => b - a)
  const bySeason = new Map(
    templates.filter((template) => template.season === season).map((template) => [template.points, template]),
  )

  function open(next: Editing) {
    setNotice(null)
    setError(null)
    setEditing(next)
  }

  function saved(points: number) {
    setEditing(null)
    setVersion((value) => value + 1)
    setNotice(`Se guardó la plantilla del certificado ${levelName(points)} ${season}.`)
    load()
  }

  async function remove(points: number) {
    if (season === null) return
    if (!window.confirm(`¿Borrar la plantilla del certificado ${levelName(points)} ${season}?`)) return
    setNotice(null)
    setError(null)
    try {
      await deleteCertificateTemplate(season, points)
      setNotice(`Se borró la plantilla del certificado ${levelName(points)} ${season}.`)
      load()
    } catch (failure) {
      setError(
        changeError(failure, { action: 'borrar la plantilla', conflict: 'No se pudo borrar.', notFound: 'La plantilla ya no existe.' }),
      )
    }
  }

  return (
    <>
      <div className="title-row">
        <h2>Plantillas de certificados</h2>
        {!editing && (
          <SeasonSelect
            seasons={options}
            value={season}
            onChange={(value) => {
              setNotice(null)
              setSeason(value)
            }}
          />
        )}
      </div>
      <p className="hint">
        Cada temporada tiene una plantilla para cada nivel. El sistema escribe el indicativo del participante y la
        fecha del certificado: la fecha de la actividad que le dio el último punto necesario.
      </p>
      {notice && <p role="status">{notice}</p>}
      {error && (
        <p className="error" role="alert">
          {error}
        </p>
      )}

      {editing ? (
        <TemplateEditor
          kind={CERTIFICATE_KIND}
          title={`Certificado ${levelName(editing.points)} ${season} (${editing.points} puntos)`}
          template={
            editing.template && {
              ...editing.template,
              imageUrl: certificateImageUrl(season, editing.points, version),
            }
          }
          save={(fields, file) => saveCertificateTemplate(season, editing.points, fields, file)}
          preview={(fields, file) => previewCertificate(fields, file, season, editing.points)}
          onDone={() => saved(editing.points)}
          onCancel={() => setEditing(null)}
        />
      ) : (
        <div className="template-grid">
          {levels.map((points) => {
            const template = bySeason.get(points) ?? null
            return (
              <figure key={points} className="template-card">
                {template ? (
                  <img
                    src={certificateImageUrl(season, points, version)}
                    alt={`Plantilla del certificado ${levelName(points)}`}
                    loading="lazy"
                  />
                ) : (
                  <div className="template-empty">Sin plantilla</div>
                )}
                <figcaption>
                  <strong>
                    <span className={levelClass(points)} aria-hidden="true" /> {levelName(points)} · {points} puntos
                  </strong>
                  <span className="row-actions">
                    <button type="button" className="link" onClick={() => open({ points, template })}>
                      {template ? 'Editar' : 'Subir la plantilla'}
                    </button>
                    {template && (
                      <button type="button" className="link" onClick={() => void remove(points)}>
                        Borrar
                      </button>
                    )}
                  </span>
                </figcaption>
              </figure>
            )
          })}
        </div>
      )}
    </>
  )
}
