import { Fragment, useMemo, useState } from 'react'
import { Link } from '@inertiajs/react'
import AppLayout from '../Layouts/AppLayout.jsx'
import { fmt } from '../lib/data.js'

const th = 'sticky top-0 z-[2] h-10 whitespace-nowrap border-b border-lineStrong bg-surface2 px-2.5 text-left text-[11px] font-semibold uppercase tracking-wider text-ink2'
const td = 'border-b border-line px-2.5 py-2 align-top'
const num = ' tnum whitespace-nowrap text-right font-mono'
const fecha = (s) => (s ? s.split('-').reverse().join('-') : '—')
const cc = (c) => (c?.length === 15 ? `${c.slice(0, 8)}.${c.slice(8, 12)}.${c.slice(12)}` : c)
const MESES = ['Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre']
// «ID4675 TRASPASO POR HOSTING TF OCTUBRE 2026 DESDE DEPTOS A UDT» → «Hosting TF octubre 2026»
const concepto = (g) => {
  const t = (g ?? '').replace(/^ID\s*\d+\s*/i, '').replace(/^TRASPASO( DE RECURSOS)?( POR)?\s*/i, '').replace(/\s+DESDE\s+.*$/i, '').trim()
  return t ? t.charAt(0) + t.slice(1).toLowerCase() : '—'
}

export default function MisCobros({ email, vistaPrevia, centros, lineas }) {
  const anios = useMemo(() => [...new Set(lineas.map((l) => l.fecha?.slice(0, 4)).filter(Boolean))].sort().reverse(), [lineas])
  const [anio, setAnio] = useState(anios[0] ?? '')
  const [centro, setCentro] = useState('')

  const lista = lineas.filter((l) => (!anio || l.fecha?.startsWith(anio)) && (!centro || l.centro_costo === centro))
  const total = lista.reduce((a, l) => a + l.monto, 0)
  const porMes = useMemo(() => {
    const m = new Map()
    for (const l of lista) {
      const k = l.fecha?.slice(0, 7) ?? 'sin fecha'
      m.set(k, [...(m.get(k) ?? []), l])
    }
    return [...m.entries()]
  }, [lista])
  const ultimo = lista[0]

  if (!centros.length) {
    return (
      <section className="panel mx-auto mt-6 max-w-xl text-center">
        <h1 className="m-0 mb-2 text-lg font-semibold">Mis cobros</h1>
        <p className="m-0 text-ink2">
          {vistaPrevia ? `El correo ${email} no tiene centros de costo asignados.` : 'Su cuenta todavía no tiene un área asignada. Pida a la Unidad de Desarrollo Tecnológico que se la asigne.'}
        </p>
      </section>
    )
  }

  return (
    <section className="flex flex-col gap-4">
      {vistaPrevia && (
        <div className="flex flex-wrap items-center gap-2 rounded-lg border border-accent bg-accentSoft px-3 py-2 text-sm text-accent">
          Vista previa: así ve esta pantalla <b>{email}</b>.
          <Link href="/accesos" className="ml-auto underline">Volver a Accesos de áreas</Link>
        </div>
      )}

      <div>
        <h1 className="m-0 text-xl font-semibold">Cobros de la UDT a su área</h1>
        <p className="m-0 mt-1 text-sm text-ink2">
          Servicios y bienes que la Unidad de Desarrollo Tecnológico ha traspasado a {centros.length === 1 ? 'su centro de costo' : 'sus centros de costo'}:{' '}
          {centros.map((c) => <span key={c.codigo} className="mr-2 whitespace-nowrap"><b>{c.nombre ?? 'Centro de costo'}</b> <span className="font-mono text-xs text-ink3">{cc(c.codigo)}</span></span>)}
        </p>
      </div>

      <div className="flex flex-wrap items-end gap-3">
        <div className="flex flex-col gap-1">
          <label className="lbl" htmlFor="anio">Año</label>
          <select id="anio" className="inp !w-auto" value={anio} onChange={(e) => setAnio(e.target.value)}>
            {anios.map((a) => <option key={a}>{a}</option>)}
            <option value="">Todos</option>
          </select>
        </div>
        {centros.length > 1 && (
          <div className="flex flex-col gap-1">
            <label className="lbl" htmlFor="centro">Centro de costo</label>
            <select id="centro" className="inp !w-auto" value={centro} onChange={(e) => setCentro(e.target.value)}>
              <option value="">Todos</option>
              {centros.map((c) => <option key={c.codigo} value={c.codigo}>{c.nombre ?? cc(c.codigo)}</option>)}
            </select>
          </div>
        )}
      </div>

      <div className="grid grid-cols-1 gap-3 sm:grid-cols-3">
        <div className="panel"><div className="lbl">Total cobrado {anio}</div><div className="tnum mt-1 font-mono text-2xl font-semibold">{fmt(total)}</div></div>
        <div className="panel"><div className="lbl">Cobros</div><div className="tnum mt-1 font-mono text-2xl font-semibold">{lista.length}</div></div>
        <div className="panel"><div className="lbl">Último cobro</div>
          <div className="mt-1 text-lg font-semibold">{ultimo ? fecha(ultimo.fecha) : '—'}</div>
          {ultimo && <div className="text-xs text-ink2">{concepto(ultimo.glosa)} · {fmt(ultimo.monto)}</div>}
        </div>
      </div>

      <div className="panel overflow-auto !p-0">
        <table className="w-full min-w-[720px] border-collapse text-sm">
          <thead><tr>
            <th className={th}>Fecha</th><th className={th}>Concepto</th>
            {centros.length > 1 && <th className={th}>Centro de costo</th>}
            <th className={th}>Comprobante</th><th className={th}>ID Webdox</th><th className={th + ' text-right'}>Monto</th>
          </tr></thead>
          <tbody>
            {porMes.map(([mes, ls]) => (
              <Fragment key={mes}>
                <tr className="bg-surface2">
                  <td colSpan={centros.length > 1 ? 5 : 4} className="border-b border-line px-2.5 py-1.5 text-xs font-semibold uppercase tracking-wider text-ink2">
                    {mes.length === 7 ? `${MESES[+mes.slice(5) - 1]} ${mes.slice(0, 4)}` : mes}
                  </td>
                  <td className="border-b border-line px-2.5 py-1.5 text-right font-mono text-xs font-semibold text-ink2">{fmt(ls.reduce((a, l) => a + l.monto, 0))}</td>
                </tr>
                {ls.map((l) => (
                  <tr key={l.id}>
                    <td className={td + ' whitespace-nowrap'}>{fecha(l.fecha)}</td>
                    <td className={td}>{concepto(l.glosa)}</td>
                    {centros.length > 1 && <td className={td}>{l.nombre ?? cc(l.centro_costo)}</td>}
                    <td className={td + ' font-mono text-xs'}>{l.comprobante ?? 'en trámite'}</td>
                    <td className={td + ' font-mono text-xs'}>ID{l.webdox_id}</td>
                    <td className={td + num}>{fmt(l.monto)}</td>
                  </tr>
                ))}
              </Fragment>
            ))}
            {!lista.length && <tr><td colSpan={6} className={td + ' text-center text-ink3'}>No hay cobros en este período.</td></tr>}
          </tbody>
        </table>
      </div>

      <p className="m-0 text-xs text-ink3">
        Cada cobro es un traspaso de recursos registrado en un comprobante contable (cargo a la cuenta 5.2.02.14.067 de su centro de costo).
        El ID de Webdox identifica la solicitud de traspaso. Ante dudas, contacte a la Unidad de Desarrollo Tecnológico.
      </p>
    </section>
  )
}

MisCobros.layout = (page) => <AppLayout>{page}</AppLayout>
