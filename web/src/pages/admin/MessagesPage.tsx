import { useEffect, useState } from 'react'
import { MessageEditor } from '../../components/MessageEditor.tsx'
import { readTemplate, resetTemplate, saveTemplate } from '../../mail.ts'
import type { MailKind, MessageText } from '../../mail.ts'
import { href } from '../../router.ts'

type Loaded = MessageText & { variables: Record<string, string> }

const KINDS: { kind: MailKind; title: string; hint: string }[] = [
  {
    kind: 'qsl',
    title: 'Mensaje general de las QSL',
    hint: 'Cada actividad empieza con este mensaje, y el administrador lo cambia para el evento.',
  },
  {
    kind: 'certificate',
    title: 'Mensaje general de los certificados',
    hint: 'Cada temporada empieza con este mensaje.',
  },
]

/**
 * The general texts of the messages (FR-MAIL-10). An activity or a season can have an own text.
 */
export function MessagesPage() {
  return (
    <>
      <p>
        <a href={href('/admin/envios')}>← Envíos</a>
      </p>
      <h2>Mensajes generales</h2>
      {KINDS.map((item) => (
        <GeneralMessage key={item.kind} {...item} />
      ))}
    </>
  )
}

function GeneralMessage({ kind, title, hint }: { kind: MailKind; title: string; hint: string }) {
  const [loaded, setLoaded] = useState<Loaded | null>(null)
  const [error, setError] = useState(false)

  useEffect(() => {
    readTemplate(kind)
      .then(setLoaded)
      .catch(() => setError(true))
  }, [kind])

  if (error) return <p className="error">No se pudo leer el mensaje.</p>
  if (!loaded) return <p>Cargando…</p>
  return (
    <>
      <p className="hint">{hint}</p>
      <MessageEditor
        key={`${loaded.subject}|${loaded.body}`}
        title={title}
        template={loaded}
        variables={loaded.variables}
        targets={[]}
        preview={() => Promise.reject(new Error('No preview'))}
        test={null}
        save={async (subject, body) => setLoaded({ ...(await saveTemplate(kind, subject, body)), variables: loaded.variables })}
        reset={async () => setLoaded({ ...(await resetTemplate(kind)), variables: loaded.variables })}
        resetLabel="Volver al mensaje original"
      />
    </>
  )
}
