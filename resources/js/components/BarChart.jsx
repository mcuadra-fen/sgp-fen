import { AB, MESES, fmt, fmtM, niceMax } from '../lib/data.js'

const W = 720, H = 240, L = 54, R = 10, T = 14, B = 28
const label = { fill: 'var(--ink-3)', fontFamily: '"IBM Plex Mono", monospace', fontSize: 11 }

/** Barras por mes dibujadas con una sola escala. */
export default function BarChart({ values }) {
  const mx = Math.max(...values)
  const top = niceMax(mx)
  const iw = W - L - R, ih = H - T - B, bw = iw / 12
  const mi = values.indexOf(mx)
  return (
    <svg viewBox={`0 0 ${W} ${H}`} className="block h-auto w-full" role="img" aria-label="Monto por mes">
      {[0, 1, 2, 3, 4].map((k) => {
        const y = T + ih - (ih * k) / 4
        return (
          <g key={k}>
            <line x1={L} x2={W - R} y1={y} y2={y} stroke="var(--line)" />
            <text x={L - 6} y={y + 4} textAnchor="end" style={label}>{fmtM((top * k) / 4)}</text>
          </g>
        )
      })}
      {values.map((v, i) => {
        const h = (ih * v) / top
        return (
          <g key={i}>
            <rect
              x={L + i * bw + bw * 0.18} y={T + ih - h} width={bw * 0.64} height={Math.max(h, 0)} rx="2"
              fill="var(--accent)" opacity={i === mi && v > 0 ? 1 : 0.62}
            >
              <title>{`${MESES[i]}: ${fmt(v)}`}</title>
            </rect>
            <text x={L + i * bw + bw / 2} y={H - 9} textAnchor="middle" style={label}>{AB[i]}</text>
          </g>
        )
      })}
    </svg>
  )
}
