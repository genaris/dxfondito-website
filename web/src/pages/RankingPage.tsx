import { useEffect, useState } from 'react'
import { readSeasons } from '../activities.ts'
import type { Seasons } from '../activities.ts'
import { levelClass, levelName } from '../program.ts'
import { readRanking, searchRanking } from '../ranking.ts'
import type { Ranking } from '../ranking.ts'
import { href, navigate } from '../router.ts'
import { SeasonSelect } from '../SeasonSelect.tsx'

type State = { kind: 'loading' } | { kind: 'ready'; ranking: Ranking } | { kind: 'error' }

/**
 * The ranking of a season (FR-PUB-1 to FR-PUB-7). Without a season in the path, the current season.
 */
export function RankingPage({ season }: { season: number | null }) {
  const [seasons, setSeasons] = useState<Seasons | null>(null)
  const [state, setState] = useState<State>({ kind: 'loading' })
  const [search, setSearch] = useState('')
  const selected = season ?? seasons?.current ?? null

  useEffect(() => {
    readSeasons()
      .then(setSeasons)
      .catch(() => setState({ kind: 'error' }))
  }, [])

  useEffect(() => {
    if (selected === null) return
    // The page has a new instance for each season in the path. Thus the first state is 'loading'.
    let active = true
    readRanking(selected)
      .then((ranking) => {
        if (active) setState({ kind: 'ready', ranking })
      })
      .catch(() => {
        if (active) setState({ kind: 'error' })
      })
    return () => {
      active = false
    }
  }, [selected])

  const options =
    seasons && selected !== null && !seasons.seasons.includes(selected)
      ? [selected, ...seasons.seasons]
      : (seasons?.seasons ?? [])

  return (
    <>
      <div className="title-row">
        <h2>Ranking {selected ?? ''}</h2>
        {state.kind === 'ready' && state.ranking.rows.length > 0 && (
          <span className="hint">{state.ranking.rows.length} participantes</span>
        )}
        {selected !== null && options.length > 0 && (
          <SeasonSelect seasons={options} value={selected} onChange={(value) => navigate(`/temporada/${value}`)} />
        )}
      </div>
      {state.kind === 'loading' && <p>Cargando…</p>}
      {state.kind === 'error' && <p className="error">No se pudo leer el ranking.</p>}
      {state.kind === 'ready' && state.ranking.rows.length === 0 && (
        <p>La temporada {state.ranking.season} todavía no tiene contactos.</p>
      )}
      {state.kind === 'ready' && state.ranking.rows.length > 0 && (
        <RankingTable ranking={state.ranking} search={search} onSearch={setSearch} />
      )}
    </>
  )
}

function RankingTable({
  ranking,
  search,
  onSearch,
}: {
  ranking: Ranking
  search: string
  onSearch: (text: string) => void
}) {
  const rows = searchRanking(ranking.rows, search)
  return (
    <>
      <label className="inline-field search">
        Buscar indicativo{' '}
        <input type="search" value={search} onChange={(event) => onSearch(event.target.value)} placeholder="LU1ABC" />
      </label>
      <div className="table-scroll">
        <table className="table ranking">
          <thead>
            <tr>
              <th className="number">Pos.</th>
              <th>Indicativo</th>
              <th className="number">Puntos</th>
              {ranking.levels.map((level) => (
                <th key={level} className="level" title={`Certificado de ${level} referencias`}>
                  {levelName(level)}
                </th>
              ))}
              <th>Referencias</th>
            </tr>
          </thead>
          <tbody>
            {rows.map((row) => (
              <tr key={row.callSign} className={row.position <= 3 ? `podium podium-${row.position}` : undefined}>
                <td className="number position">{row.position}</td>
                <td>
                  <a href={href(`/participante/${row.callSign}`)}>{row.callSign}</a>
                </td>
                <td className="number">
                  <strong>{row.points}</strong>
                </td>
                {ranking.levels.map((level) => (
                  <td key={level} className="level">
                    {row.levels.includes(level) && (
                      <span className={levelClass(level)} role="img" aria-label={`Certificado ${levelName(level)}`} />
                    )}
                  </td>
                ))}
                <td className="references">
                  {row.references.map((code) => (
                    <span key={code} className="tag">
                      {code}
                    </span>
                  ))}
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>
      {rows.length === 0 && <p>Ningún indicativo del ranking contiene «{search.trim()}».</p>}
    </>
  )
}
