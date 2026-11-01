import { Fragment, useMemo, useState } from 'react'
import { Link, router } from '@inertiajs/react'
import AppLayout from '../Layouts/AppLayout.jsx'
import { fmt } from '../lib/data.js'

const PILL = { finished: 'pill pill-ok', canceled: 'pill pill-bad', rejected: 'pill pill-bad' }
const th = 'sticky top-0 z-[2] h-10 whitespace-nowrap border-b border-lineStrong bg-surface2 px-2.5 text-left text-[11px] font-semibold uppercase tracking-wider text-ink2'
const td = 'border-b border-line px-2.5 py-2 align-top'
const pie = 'sticky bottom-0 border-t border-lineStrong bg-surface2 px-2.5 py-2.5 font-semibold'
const num = ' tnum whitespace-nowrap text-right font-mono'
const fecha = (s) => (s ? s.split('-').reverse().join('-') : '—')
const cc = (c) => (c?.length === 15 ? `${c.slice(0, 8)}.${c.slice(8, 12)}.${c.slice(12)}` : c)
const nombre = (l) => l.nombre ?? 'Centro de costo sin nombre'
// Glosa sin el «ID#### TRASPASO POR» del comienzo
const concepto = (g) => (g ?? '').replace(/^ID\s*\d+\s*/i, '').replace(/^TRASPASO( DE RECURSOS)?( POR)?\s*/i, '') || '—'

function Filtro({ id, label, children }) {
  return <div className="flex min-w-0 max-w-[260px] flex-[1_1_150px] flex-col gap-1"><label className="lbl" htmlFor={id}>{label}</label>{children}</div>
}

/** Lo cobrado por la UDT, agrupado por área (centro de costo) o como detalle por comprobante. */
function Cobros({ lineas }) {
  const [q, setQ] = useState('')
  const [mes, setMes] = useState('')
  const [agrupar, setAgrupar] = useState(true)
  const [abierta, setAbierta] = useState(null)

  const meses = useMemo(() => [...new Set(lineas.map((l) => l.fecha?.slice(0, 7)).filter(Boolean))].sort().reverse(), [lineas])
  const lista = useMemo(() => {
    const t = q.toLowerCase()
    return lineas.filter((l) => (!mes || l.fecha?.startsWith(mes)) &&
      (!t || [l.nombre, l.area, l.centro_costo, l.webdox_id, l.comprobante, l.glosa].join(' ').toLowerCase().includes(t)))
  }, [lineas, q, mes])
  const grupos = useMemo(() => {
    const m = new Map()
    for (const l of lista) {
      const g = m.get(l.centro_costo) ?? { ...l, total: 0, n: 0, ids: new Set(), lineas: [] }
      g.total += l.monto; g.n++; g.ids.add(l.webdox_id); g.lineas.push(l)
      m.set(l.centro_costo, g)
    }
    return [...m.values()].sort((a, b) => b.total - a.total)
  }, [lista])
  const total = lista.reduce((a, l) => a + l.monto, 0)

  return (
    <>
      <div className="flex flex-wrap items-end gap-3">
        <Filtro id="q" label="Buscar"><input id="q" className="input" value={q} onChange={(e) => setQ(e.target.value)} placeholder="Área, centro de costo, ID…" /></Filtro>
        <Filtro id="mes" label="Mes del comprobante">
          <select id="mes" className="input" value={mes} onChange={(e) => setMes(e.target.value)}>
            <option value="">Todos</option>
            {meses.map((m) => <option key={m} value={m}>{m.split('-').reverse().join('-')}</option>)}
          </select>
        </Filtro>
        <label className="flex items-center gap-2 pb-2 text-sm">
          <input type="checkbox" checked={agrupar} onChange={(e) => setAgrupar(e.target.checked)} /> Agrupar por área
        </label>
      </div>

      <div className="card overflow-auto">
        {agrupar ? (
          <table className="w-full min-w-[820px] border-collapse text-sm">
            <thead><tr>
              <th className={th}>Área</th><th className={th}>Centro de costo</th>
              <th className={th + ' text-right'}>Cobros</th><th className={th}>IDs Webdox</th>
              <th className={th + ' text-right'}>Total</th><th className={th + ' text-right'}>% del total</th>
            </tr></thead>
            <tbody>
              {grupos.map((g) => (
                <Fragment key={g.centro_costo}>
                  <tr className={'cursor-pointer hover:bg-surface2 ' + (abierta === g.centro_costo ? 'bg-surface2' : '')}
                    onClick={() => setAbierta(abierta === g.centro_costo ? null : g.centro_costo)}>
                    <td className={td}>{nombre(g)}{g.area && g.area !== g.nombre && <div className="text-[11px] text-ink3">{g.area}</div>}</td>
                    <td className={td + ' font-mono text-xs'}>{cc(g.centro_costo)}</td>
                    <td className={td + num}>{g.n} {abierta === g.centro_costo ? '▲' : '▼'}</td>
                    <td className={td + ' text-xs text-ink2'}>{[...g.ids].filter(Boolean).map((i) => `ID${i}`).join(', ')}</td>
                    <td className={td + num + ' font-semibold'}>{fmt(g.total)}</td>
                    <td className={td + num}>{total ? ((g.total / total) * 100).toFixed(1) : 0}%</td>
                  </tr>
                  {abierta === g.centro_costo && (
                    <tr><td colSpan={6} className="border-b border-line bg-surface2 px-4 py-2">
                      <table className="w-full border-collapse text-xs"><tbody>
                        {g.lineas.map((l) => (
                          <tr key={l.id}>
                            <td className="px-2 py-1 whitespace-nowrap">{fecha(l.fecha)}</td>
                            <td className="px-2 py-1 font-mono">ID{l.webdox_id}</td>
                            <td className="px-2 py-1 font-mono">{l.comprobante ?? 'asiento .txt'}</td>
                            <td className="px-2 py-1">{concepto(l.glosa)}</td>
                            <td className={'px-2 py-1' + num}>{fmt(l.monto)}</td>
                          </tr>
                        ))}
                      </tbody></table>
                    </td></tr>
                  )}
                </Fragment>
              ))}
              {!grupos.length && <tr><td colSpan={6} className={td + ' text-center text-ink3'}>No hay traspasos con estos filtros.</td></tr>}
            </tbody>
            <tfoot><tr>
              <td className={pie} colSpan={2}>{grupos.length} centros de costo</td><td className={pie + num}>{lista.length}</td><td className={pie} />
              <td className={pie + num}>{fmt(total)}</td><td className={pie} />
            </tr></tfoot>
          </table>
        ) : (
          <table className="w-full min-w-[980px] border-collapse text-sm">
            <thead><tr>
              {['Fecha', 'ID Webdox', 'Comprobante', 'Concepto', 'Área', 'Centro de costo'].map((h) => <th key={h} className={th}>{h}</th>)}
              <th className={th + ' text-right'}>Monto</th>
            </tr></thead>
            <tbody>
              {lista.map((l) => (
                <tr key={l.id}>
                  <td className={td + ' whitespace-nowrap'}>{fecha(l.fecha)}</td>
                  <td className={td + ' font-mono'}>ID{l.webdox_id}</td>
                  <td className={td + ' font-mono text-xs'}>{l.comprobante ?? 'asiento .txt'}</td>
                  <td className={td}>{concepto(l.glosa)}</td>
                  <td className={td}>{nombre(l)}</td>
                  <td className={td + ' font-mono text-xs'}>{cc(l.centro_costo)}</td>
                  <td className={td + num + ' font-semibold'}>{fmt(l.monto)}</td>
                </tr>
              ))}
              {!lista.length && <tr><td colSpan={7} className={td + ' text-center text-ink3'}>No hay traspasos con estos filtros.</td></tr>}
            </tbody>
            <tfoot><tr><td className={pie} colSpan={6}>{lista.length} líneas</td><td className={pie + num}>{fmt(total)}</td></tr></tfoot>
          </table>
        )}
      </div>
    </>
  )
}

/** Solicitudes «Traspaso (Cobros DEA)» con todos sus campos de Webdox; cada una enlaza a su detalle (resumen del Excel). */
function Solicitudes({ solicitudes, estados }) {
  const [abierta, setAbierta] = useState(null)
  const [q, setQ] = useState('')
  const [anio, setAnio] = useState('')
  const [estado, setEstado] = useState('')
  const anios = useMemo(() => [...new Set(solicitudes.map((s) => (s.fecha_memo ?? s.creada)?.slice(0, 4)).filter(Boolean))].sort().reverse(), [solicitudes])
  const lista = useMemo(() => {
    const t = q.toLowerCase()
    return solicitudes.filter((s) => (!anio || (s.fecha_memo ?? s.creada)?.startsWith(anio)) && (!estado || s.estado === estado) &&
      (!t || [s.webdox_id, s.descripcion, s.correlativo, s.solicitante_nombre, s.aprobador, s.ceco_ingreso, s.titulo, ...s.adjuntos.map((a) => a.nombre)].join(' ').toLowerCase().includes(t)))
  }, [solicitudes, q, anio, estado])
  const total = lista.filter((s) => s.estado !== 'canceled').reduce((a, s) => a + (s.total ?? 0), 0)
  const cols = 11

  return (
    <>
      <div className="flex flex-wrap items-end gap-3">
        <Filtro id="sq" label="Buscar"><input id="sq" className="input" value={q} onChange={(e) => setQ(e.target.value)} placeholder="ID, descripción, correlativo…" /></Filtro>
        <Filtro id="sa" label="Año">
          <select id="sa" className="input" value={anio} onChange={(e) => setAnio(e.target.value)}>
            <option value="">Todos</option>{anios.map((a) => <option key={a}>{a}</option>)}
          </select>
        </Filtro>
        <Filtro id="se" label="Estado">
          <select id="se" className="input" value={estado} onChange={(e) => setEstado(e.target.value)}>
            <option value="">Todos</option>
            {[...new Set(solicitudes.map((s) => s.estado))].map((e) => <option key={e} value={e}>{estados[e] ?? e}</option>)}
          </select>
        </Filtro>
      </div>
      <div className="card overflow-auto">
        <table className="w-full min-w-[1300px] border-collapse text-sm">
          <thead><tr>
            {['ID Webdox', 'Descripción', 'Estado', 'Creada', 'Fecha memo', 'Correlativo', 'Solicitante', 'Aprobador(a)', 'CECO ingreso'].map((h) => <th key={h} className={th}>{h}</th>)}
            <th className={th + ' text-right'}>Total de traspaso</th><th className={th}>Detalle</th>
          </tr></thead>
          <tbody>
            {lista.map((s) => (
              <Fragment key={s.id}>
                <tr className={'cursor-pointer hover:bg-surface2 ' + (s.estado === 'canceled' ? 'text-ink3' : '')} onClick={() => setAbierta(abierta === s.id ? null : s.id)}>
                  <td className={td + ' font-mono'}>{s.webdox_id}</td>
                  <td className={td}>{s.descripcion ?? s.titulo}</td>
                  <td className={td}><span className={PILL[s.estado] ?? 'pill'}>{estados[s.estado] ?? s.estado}</span></td>
                  <td className={td + ' whitespace-nowrap'}>{fecha(s.creada)}</td>
                  <td className={td + ' whitespace-nowrap'}>{fecha(s.fecha_memo)}</td>
                  <td className={td + ' text-center'}>{s.correlativo ?? '—'}</td>
                  <td className={td}>{s.solicitante_nombre ?? s.solicitante ?? '—'}</td>
                  <td className={td}>{s.aprobador ?? '—'}{s.cargo_aprobador && <div className="text-[11px] text-ink3">{s.cargo_aprobador}</div>}</td>
                  <td className={td + ' font-mono text-xs'}>{cc(s.ceco_ingreso)}</td>
                  <td className={td + num + ' font-semibold'}>{s.total !== null ? fmt(s.total) : '—'}</td>
                  <td className={td + ' whitespace-nowrap'}>
                    <Link href={`/traspasos/${s.webdox_id}`} className="text-accent underline" onClick={(e) => e.stopPropagation()}>
                      {s.excel ? 'Ver resumen Excel' : 'Ver solicitud'}
                    </Link>
                  </td>
                </tr>
                {abierta === s.id && (
                  <tr><td colSpan={cols} className="border-b border-line bg-surface2 px-4 py-2 text-xs">
                    <div className="mb-1 font-semibold">{s.titulo}</div>
                    <div className="grid gap-x-6 gap-y-0.5 sm:grid-cols-2">
                      {Object.entries(s.atributos).filter(([, v]) => typeof v !== 'object').map(([k, v]) => (
                        <div key={k}><span className="text-ink2">{k}:</span> {String(v ?? '—')}</div>
                      ))}
                    </div>
                    {s.adjuntos.length > 0 && (
                      <div className="mt-1.5"><span className="text-ink2">Adjuntos en Webdox:</span> {s.adjuntos.map((a) => a.nombre).join(' · ')}</div>
                    )}
                  </td></tr>
                )}
              </Fragment>
            ))}
            {!lista.length && <tr><td colSpan={cols} className={td + ' text-center text-ink3'}>No hay solicitudes con estos filtros. Use «Sincronizar con Webdox» si faltan.</td></tr>}
          </tbody>
          <tfoot><tr>
            <td className={pie} colSpan={9}>{lista.length} solicitudes (el total no suma las canceladas)</td>
            <td className={pie + num}>{fmt(total)}</td><td className={pie} />
          </tr></tfoot>
        </table>
      </div>
    </>
  )
}

export default function Traspasos({ lineas, solicitudes, estados, sincronizado }) {
  const [vista, setVista] = useState('solicitudes')
  const [cargando, setCargando] = useState(null)
  const accion = (url, cual) => router.post(url, {}, { preserveScroll: true, onStart: () => setCargando(cual), onFinish: () => setCargando(null) })
  const pestana = (v) => 'btn btn-sm ' + (vista === v ? 'border-accent text-accent' : '')

  return (
    <section className="flex flex-col gap-3">
      <div className="flex flex-wrap items-center gap-2">
        <button className={pestana('solicitudes')} onClick={() => setVista('solicitudes')}>Solicitudes Webdox ({solicitudes.length})</button>
        <button className={pestana('cobros')} onClick={() => setVista('cobros')}>Cobros por área</button>
        <div className="flex-1" />
        <button className="btn" onClick={() => accion('/traspasos/leer', 'leer')} disabled={!!cargando}>
          {cargando === 'leer' ? 'Leyendo…' : 'Leer comprobantes'}
        </button>
        <div className="flex flex-col items-end gap-0.5">
          <button className="btn btn-primary" onClick={() => accion('/pagos/sincronizar', 'wd')} disabled={!!cargando}>
            {cargando === 'wd' ? 'Sincronizando…' : 'Sincronizar con Webdox'}
          </button>
          <span className="text-[11px] text-ink3">
            {sincronizado ? `Última: ${new Date(sincronizado.replace(' ', 'T')).toLocaleString('es-CL')}` : 'Aún no se ha sincronizado'}
          </span>
        </div>
      </div>

      {vista === 'cobros' ? <Cobros lineas={lineas} /> : <Solicitudes solicitudes={solicitudes} estados={estados} />}

      <p className="m-0 text-xs text-ink3">
        Solicitudes de Webdox con formulario «Traspaso (Cobros DEA)» de la Unidad de Desarrollo Tecnológico. Los montos salen de sus
        comprobantes contables «T2026…pdf» (o del asiento .txt mientras no llega el comprobante) de la carpeta compartida «Traspaso de
        recursos entre centros de costos»: cargo en la cuenta 5.2.02.14.067 al centro de costo del área y abono en la 6.2.03.09.067 a la UDT.
      </p>
    </section>
  )
}

Traspasos.layout = (page) => <AppLayout>{page}</AppLayout>
