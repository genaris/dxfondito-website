import { useCallback, useEffect, useState } from 'react'
import { listAccounts } from '../accounts.ts'
import type { Account } from '../accounts.ts'
import { changeError } from '../messages.ts'
import { deleteTemplate, previewTemplate, QSL_KIND, readTemplates, saveTemplate, templateImageUrl } from '../qsl.ts'
import type { QslTemplate } from '../qsl.ts'
import type { User } from '../useSession.ts'
import { TemplateEditor } from './TemplateEditor.tsx'

type ListState = { kind: 'loading' } | { kind: 'ready'; templates: QslTemplate[] } | { kind: 'error' }
type Editing = { operatorId: number; callSign: string; template: QslTemplate | null }

/**
 * The QSL card templates of an activity, for the signed-in users (FR-QSL-1, FR-QSL-2).
 * An operator controls the own template. An administrator controls all templates.
 */
export function QslTemplatesSection({ activityId, user }: { activityId: number; user: User }) {
  const isAdministrator = user.role === 'administrator'
  const [list, setList] = useState<ListState>({ kind: 'loading' })
  const [accounts, setAccounts] = useState<Account[]>([])
  const [newOperatorId, setNewOperatorId] = useState<number>(user.id)
  const [editing, setEditing] = useState<Editing | null>(null)
  const [version, setVersion] = useState(0)
  const [notice, setNotice] = useState<string | null>(null)
  const [error, setError] = useState<string | null>(null)

  const load = useCallback(() => {
    readTemplates(activityId)
      .then((templates) => setList({ kind: 'ready', templates }))
      .catch(() => setList({ kind: 'error' }))
  }, [activityId])

  useEffect(load, [load])

  useEffect(() => {
    if (!isAdministrator) return
    listAccounts()
      .then((all) => setAccounts(all.filter((account) => account.active)))
      .catch(() => setAccounts([]))
  }, [isAdministrator])

  if (list.kind === 'loading') return null
  if (list.kind === 'error') return <p className="error">No se pudieron leer las plantillas QSL.</p>

  const withTemplate = new Set(list.templates.map((template) => template.operator.id))
  const candidates = isAdministrator
    ? accounts.filter((account) => !withTemplate.has(account.id))
    : withTemplate.has(user.id)
      ? []
      : [{ id: user.id, callSign: user.callSign }]
  const selectedNew = candidates.find((account) => account.id === newOperatorId) ?? candidates[0]

  function open(next: Editing) {
    setNotice(null)
    setError(null)
    setEditing(next)
  }

  function saved(callSign: string) {
    setEditing(null)
    setVersion((value) => value + 1)
    setNotice(`Se guardó la plantilla QSL de ${callSign}.`)
    load()
  }

  async function remove(template: QslTemplate) {
    if (!window.confirm(`¿Borrar la plantilla QSL de ${template.operator.callSign}?`)) return
    setNotice(null)
    setError(null)
    try {
      await deleteTemplate(activityId, template.operator.id)
      setNotice(`Se borró la plantilla QSL de ${template.operator.callSign}.`)
      load()
    } catch (failure) {
      setError(
        changeError(failure, { action: 'borrar la plantilla', conflict: 'No se pudo borrar.', notFound: 'La plantilla ya no existe.' }),
      )
    }
  }

  return (
    <section>
      <h3>Plantillas QSL</h3>
      <p className="hint">
        Cada operador tiene su propia QSL para esta actividad. El participante recibe una QSL por cada contacto, con
        la plantilla del operador con el que habló. Los contactos repetidos con la misma referencia no suman puntos.
      </p>
      {notice && <p role="status">{notice}</p>}
      {error && (
        <p className="error" role="alert">
          {error}
        </p>
      )}

      {editing ? (
        <TemplateEditor
          kind={QSL_KIND}
          title={`Plantilla QSL de ${editing.callSign}`}
          template={
            editing.template && {
              ...editing.template,
              imageUrl: templateImageUrl(activityId, editing.operatorId, version),
            }
          }
          save={(fields, file) => saveTemplate(activityId, editing.operatorId, fields, file)}
          preview={(fields, file) => previewTemplate(fields, file, activityId, editing.operatorId)}
          onDone={() => saved(editing.callSign)}
          onCancel={() => setEditing(null)}
        />
      ) : (
        <>
          {list.templates.length === 0 && <p>La actividad todavía no tiene plantillas QSL.</p>}
          <div className="template-grid">
            {list.templates.map((template) => (
              <figure key={template.operator.id} className="template-card">
                <img
                  src={templateImageUrl(activityId, template.operator.id, version)}
                  alt={`Plantilla QSL de ${template.operator.callSign}`}
                  loading="lazy"
                />
                <figcaption>
                  <strong>{template.operator.callSign}</strong>
                  {template.canEdit && (
                    <span className="row-actions">
                      <button
                        type="button"
                        className="link"
                        onClick={() => open({ operatorId: template.operator.id, callSign: template.operator.callSign, template })}
                      >
                        Editar
                      </button>
                      <button type="button" className="link" onClick={() => void remove(template)}>
                        Borrar
                      </button>
                    </span>
                  )}
                </figcaption>
              </figure>
            ))}
          </div>
          {selectedNew && (
            <div className="actions new-template">
              {isAdministrator && (
                <label className="inline-field">
                  Operador{' '}
                  <select value={selectedNew.id} onChange={(event) => setNewOperatorId(Number(event.target.value))}>
                    {candidates.map((account) => (
                      <option key={account.id} value={account.id}>
                        {account.callSign}
                      </option>
                    ))}
                  </select>
                </label>
              )}
              <button
                type="button"
                onClick={() => open({ operatorId: selectedNew.id, callSign: selectedNew.callSign, template: null })}
              >
                {isAdministrator ? 'Nueva plantilla QSL' : 'Subir mi plantilla QSL'}
              </button>
            </div>
          )}
        </>
      )}
    </section>
  )
}
