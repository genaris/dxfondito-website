export function SeasonSelect({
  seasons,
  value,
  onChange,
}: {
  seasons: number[]
  value: number
  onChange: (season: number) => void
}) {
  return (
    <label className="inline-field">
      Temporada{' '}
      <select value={value} onChange={(event) => onChange(Number(event.target.value))}>
        {seasons.map((season) => (
          <option key={season} value={season}>
            {season}
          </option>
        ))}
      </select>
    </label>
  )
}
