import { useEffect, useState } from 'react'
import { Link } from '@inertiajs/react'
import { MESES, fmt, parse, sum } from '../lib/data.js'

/** Celda de monto: se edita como texto y se confirma al salir de la celda o con Enter. */
function Cell({ value, label, onCommit }) {
  const [text, setText] = useState(fmt(value))
  const [focused, setFocused] = useState(false)
  useEffect(() => { if (!focused) setText(fmt(value)) }, [value, focused])
  return (
    <input
      className="tnum w-[92px] rounded border border-transparent bg-transparent px-1.5 py-[5px] text-right font-mono hover:border-lineStrong focus:border-accent focus:bg-surface focus:outline-none focus:ring-2 focus:ring-accentSoft"
      inputMode="numeric"
      aria-label={label}
      value={text}
      onFocus={(e) => { setFocused(true); e.target.select() }}
      onChange={(e) => setText(e.target.value)}
      onBlur={() => {
        setFocused(false)
        const n = parse(text)
        setText(fmt(n))
        if (n !== value) onCommit(n)
      }}
      onKeyDown={(e) => { if (e.key === 'Enter') e.target.blur() }}
    />
  )
}

const th = 'sticky top-0 z-[3] h-10 whitespace-nowrap border-b border-lineStrong bg-surface2 px-2.5 text-left text-[11px] font-semibold uppercase tracking-wider text-ink2'
const td = 'h-10 whitespace-nowrap border-b border-line px-2.5 group-hover:bg-surface2'
const pie = 'sticky bottom-0 z-[2] border-t border-lineStrong bg-surface2'

/**
 * Tabla del presupuesto. Recibe las partidas ya filtradas y avisa los cambios por callbacks;
 * no sabe nada de Inertia ni del servidor.
 */
export default function Grilla({ partidas, onCell, onDuplicar, onEliminar }) {
  const [armado, setArmado] = useState(null)
  const meses = Array(12).fill(0)
  partidas.forEach((r) => r.meses.forEach((v, i) => { meses[i] += v }))

  const eliminar = (r) => {
    if (armado !== r.id) {
      setArmado(r.id)
      setTimeout(() => setArmado((a) => (a === r.id ? null : a)), 3000)
      return
    }
    setArmado(null)
    onEliminar(r)
  }

  return (
    <div className="max-h-[calc(100vh-230px)] min-h-[200px] overflow-auto rounded-lg border border-line bg-surface">
      <table className="w-max min-w-full border-separate border-spacing-0">
        <thead>
          <tr>
            <th className={th + ' sticky left-0 z-[4] min-w-[300px] max-w-[340px] shadow-[1px_0_0_var(--line)]'}>Cuenta · unidad · actividad</th>
            <th className={th}>Subactividad</th>
            <th className={th}>Clasificación SIGMA</th>
            {MESES.map((m) => <th key={m} className={th + ' text-right'}>{m}</th>)}
            <th className={th + ' text-right'}>Monto total</th>
            <th className={th}>Códigos</th>
            <th className={th}></th>
          </tr>
        </thead>
        <tbody>
          {partidas.length === 0 && (
            <tr><td colSpan={18} className="p-8 text-center text-ink2">No hay partidas con estos filtros.</td></tr>
          )}
          {partidas.map((r) => {
            const t = sum(r.meses)
            return (
              <tr key={r.id} className="group">
                <td className={td + ' sticky left-0 z-[2] min-w-[300px] max-w-[340px] whitespace-normal bg-surface shadow-[1px_0_0_var(--line)]'} title={r.dpto}>
                  <div className="pt-1 font-medium leading-tight">
                    {r.cuenta} {r.es_ejemplo && <span className="pill pill-warn">Ejemplo</span>}
                  </div>
                  <div className="pb-1 text-xs leading-tight text-ink3">{r.unidad} · {r.actividad}</div>
                </td>
                <td className={td}>{r.subactividad}</td>
                <td className={td}><span className="pill">{r.sigma}</span></td>
                {r.meses.map((v, i) => (
                  <td key={i} className={td + ' text-right'}>
                    <Cell value={v} label={`${MESES[i]}, ${r.cuenta}`} onCommit={(n) => onCell(r.id, i, n)} />
                  </td>
                ))}
                <td className={td + ' tnum bg-surface2 text-right font-mono font-semibold ' + (t === 0 ? 'text-warn' : '')}>{fmt(t)}</td>
                <td className={td + ' font-mono text-xs leading-tight text-ink2'}>{r.dep_codigo}<br />{r.un_codigo}</td>
                <td className={td}>
                  <div className="flex gap-1">
                    <Link href={`/partidas/${r.id}/edit`} className="btn btn-sm">Editar</Link>
                    <button className="btn btn-sm" onClick={() => onDuplicar(r)}>Duplicar</button>
                    <button className={'btn btn-sm ' + (armado === r.id ? 'btn-danger' : '')} onClick={() => eliminar(r)}>
                      {armado === r.id ? '¿Seguro?' : 'Eliminar'}
                    </button>
                  </div>
                </td>
              </tr>
            )
          })}
        </tbody>
        {partidas.length > 0 && (
          <tfoot>
            <tr className="font-semibold">
              <td className="sticky bottom-0 left-0 z-[3] border-t border-lineStrong bg-surface2 px-2.5 py-2.5 shadow-[1px_0_0_var(--line)]">
                Total ({partidas.length} partidas)
              </td>
              <td className={pie} /><td className={pie} />
              {meses.map((v, i) => (
                <td key={i} className={pie + ' tnum px-2.5 text-right font-mono'}>{fmt(v)}</td>
              ))}
              <td className={pie + ' tnum px-2.5 text-right font-mono'}>{fmt(sum(meses))}</td>
              <td className={pie} /><td className={pie} />
            </tr>
          </tfoot>
        )}
      </table>
    </div>
  )
}
