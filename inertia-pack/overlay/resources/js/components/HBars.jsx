import { fmt } from '../lib/data.js'

/** Barras horizontales con monto y porcentaje; `data` es una lista de [nombre, monto]. */
export default function HBars({ data, grand }) {
  if (!data.length) return <div className="text-xs text-ink3">Sin datos.</div>
  const max = Math.max(1, ...data.map((d) => d[1]))
  return (
    <div className="flex flex-col gap-3">
      {data.map(([k, v]) => (
        <div key={k}>
          <div className="flex justify-between gap-3 text-[13px]">
            <span className="min-w-0 break-words">{k}</span>
            <span className="tnum font-mono">
              {fmt(v)} <span className="text-xs text-ink3">{grand ? Math.round((v / grand) * 100) : 0}%</span>
            </span>
          </div>
          <div className="mt-1 h-2 overflow-hidden rounded-full border border-line bg-surface2">
            <i className="block h-full bg-accent" style={{ width: `${(v / max) * 100}%` }} />
          </div>
        </div>
      ))}
    </div>
  )
}
