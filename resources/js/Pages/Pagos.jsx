import { Fragment, useMemo, useState } from 'react'
import { Link, router } from '@inertiajs/react'
import AppLayout from '../Layouts/AppLayout.jsx'
import { fmt } from '../lib/data.js'

const PILL = { finished: 'pill pill-ok', canceled: 'pill pill-bad', rejected: 'pill pill-bad' }
const th = 'sticky top-0 z-[2] h-10 whitespace-nowrap border-b border-lineStrong bg-surface2 px-2.5 text-left text-[11px] font-semibold uppercase tracking-wider text-ink2'
const td = 'border-b border-line px-2.5 py-2 align-top'
const pie = 'sticky bottom-0 border-t border-lineStrong bg-surface2 px-2.5 py-2.5'
const num = ' tnum whitespace-nowrap text-right font-mono'
const fecha = (s) => (s ? s.split('-').reverse().join('-') : '—')
const filtro = 'flex min-w-0 max-w-[260px] flex-[1_1_150px] flex-col gap-1'

function Filtro({ id, label, children }) {
  return <div className={filtro}><label className="lbl" htmlFor={id}>{label}</label>{children}</div>
}

/** Facturas pagadas a proveedores según los certificados de pago (una fila por factura, o agrupadas). */
function Proveedores({ lineas }) {
  const [q, setQ] = useState('')
  const [anio, setAnio] = useState('')
  const [agrupar, setAgrupar] = useState(false)

  const anios = useMemo(() => [...new Set(lineas.map((l) => l.fecha?.slice(0, 4)).filter(Boolean))].sort().reverse(), [lineas])
  const lista = useMemo(() => {
    const t = q.toLowerCase()
    return lineas.filter((l) => (!anio || l.fecha?.startsWith(anio)) &&
      (!t || [l.proveedor, l.rut, l.factura, l.webdox_id, l.numero, l.cuenta, l.obs].join(' ').toLowerCase().includes(t)))
  }, [lineas, q, anio])

  // Por proveedor (RUT, o nombre si no hay RUT): cantidad de facturas y totales
  const grupos = useMemo(() => {
    const m = new Map()
    lista.forEach((l) => {
      const k = l.rut || l.proveedor
      const g = m.get(k) ?? { proveedor: l.proveedor, rut: l.rut, facturas: 0, neto: 0, total: 0, ids: new Set(), ultima: '' }
      g.facturas++; g.neto += l.neto; g.total += l.total; g.ids.add(l.webdox_id)
      if ((l.fecha ?? '') > g.ultima) g.ultima = l.fecha
      m.set(k, g)
    })
    return [...m.values()].sort((a, b) => b.total - a.total)
  }, [lista])
  const neto = lista.reduce((s, l) => s + l.neto, 0)
  const total = lista.reduce((s, l) => s + l.total, 0)

  return (
    <>
      <div className="flex flex-wrap items-end gap-2.5">
        <Filtro id="lq" label="Buscar">
          <input id="lq" type="search" className="inp" placeholder="Proveedor, RUT, factura, ID Webdox…" value={q} onChange={(e) => setQ(e.target.value)} />
        </Filtro>
        <Filtro id="la" label="Año del certificado">
          <select id="la" className="inp" value={anio} onChange={(e) => setAnio(e.target.value)}>
            <option value="">Todos</option>
            {anios.map((a) => <option key={a}>{a}</option>)}
          </select>
        </Filtro>
        <label className="flex items-center gap-2 pb-2 text-[13px]">
          <input type="checkbox" className="h-4 w-4 accent-[var(--accent)]" checked={agrupar} onChange={(e) => setAgrupar(e.target.checked)} />
          Agrupar por proveedor
        </label>
      </div>

      <div className="max-h-[calc(100vh-290px)] min-h-[200px] overflow-auto rounded-lg border border-line bg-surface">
        {agrupar ? (
          <table className="w-full min-w-[760px] border-separate border-spacing-0 text-[13px]">
            <thead><tr>
              <th className={th}>Proveedor</th><th className={th}>RUT</th><th className={th + ' text-right'}>Facturas</th>
              <th className={th}>Solicitudes Webdox</th><th className={th}>Último pago</th>
              <th className={th + ' text-right'}>Neto</th><th className={th + ' text-right'}>Total</th>
            </tr></thead>
            <tbody>
              {grupos.map((g) => (
                <tr key={g.rut || g.proveedor} className="hover:[&>td]:bg-surface2">
                  <td className={td + ' font-medium'}>{g.proveedor}</td>
                  <td className={td + ' whitespace-nowrap font-mono text-xs'}>{g.rut ?? '—'}</td>
                  <td className={td + num}>{g.facturas}</td>
                  <td className={td + ' font-mono text-xs text-ink2'}>{[...g.ids].sort((a, b) => b - a).map((i) => `ID${i}`).join(', ')}</td>
                  <td className={td + ' whitespace-nowrap font-mono text-xs'}>{fecha(g.ultima)}</td>
                  <td className={td + num}>{fmt(g.neto)}</td>
                  <td className={td + num + ' font-semibold'}>{fmt(g.total)}</td>
                </tr>
              ))}
            </tbody>
            {grupos.length > 0 && (
              <tfoot><tr className="font-semibold">
                <td colSpan={2} className={pie}>Total ({grupos.length} proveedores)</td>
                <td className={pie + num}>{lista.length}</td><td colSpan={2} className={pie} />
                <td className={pie + num}>{fmt(neto)}</td><td className={pie + num}>{fmt(total)}</td>
              </tr></tfoot>
            )}
          </table>
        ) : (
          <table className="w-full min-w-[1200px] border-separate border-spacing-0 text-[13px]">
            <thead><tr>
              <th className={th}>ID Webdox</th><th className={th}>Certificado</th><th className={th}>Fecha</th>
              <th className={th}>Proveedor</th><th className={th}>RUT</th><th className={th}>Factura / NC</th>
              <th className={th + ' text-right'}>Neto</th><th className={th + ' text-right'}>IVA</th><th className={th + ' text-right'}>Total</th>
              <th className={th}>Cuenta</th><th className={th}>Centro de costo</th><th className={th}>Observación</th>
            </tr></thead>
            <tbody>
              {lista.length === 0 && (
                <tr><td colSpan={12} className="p-8 text-center text-ink2">
                  {lineas.length ? 'No hay facturas con estos filtros.' : 'Todavía no hay certificados. Pulse «Leer certificados».'}
                </td></tr>
              )}
              {lista.map((l) => (
                <tr key={l.id} className="hover:[&>td]:bg-surface2">
                  <td className={td + ' whitespace-nowrap font-mono font-semibold'}>ID{l.webdox_id}</td>
                  <td className={td + ' whitespace-nowrap font-mono text-xs'}>{l.numero ?? '—'}</td>
                  <td className={td + ' whitespace-nowrap font-mono text-xs'}>{fecha(l.fecha)}</td>
                  <td className={td + ' font-medium'}>{l.proveedor}</td>
                  <td className={td + ' whitespace-nowrap font-mono text-xs'}>{l.rut ?? '—'}</td>
                  <td className={td + ' font-mono text-xs'}>{l.factura ?? '—'}</td>
                  <td className={td + num}>{fmt(l.neto)}</td>
                  <td className={td + num}>{fmt(l.iva)}</td>
                  <td className={td + num + ' font-semibold'}>{fmt(l.total)}</td>
                  <td className={td + ' font-mono text-xs'}>{l.cuenta ?? '—'}</td>
                  <td className={td + ' font-mono text-xs'}>{l.centro_costo ?? '—'}</td>
                  <td className={td + ' max-w-[260px] text-xs text-ink2'}>{l.obs ?? ''}</td>
                </tr>
              ))}
            </tbody>
            {lista.length > 0 && (
              <tfoot><tr className="font-semibold">
                <td colSpan={6} className={pie}>Total ({lista.length} facturas{lista.length < lineas.length ? ' filtradas' : ''})</td>
                <td className={pie + num}>{fmt(neto)}</td><td className={pie} /><td className={pie + num}>{fmt(total)}</td>
                <td colSpan={3} className={pie} />
              </tr></tfoot>
            )}
          </table>
        )}
      </div>
    </>
  )
}

/** Solicitudes «Pago a proveedores» de la UDT, copiadas desde Webdox. */
function Solicitudes({ pagos, estados }) {
  const [q, setQ] = useState('')
  const [estado, setEstado] = useState('')
  const [anio, setAnio] = useState('')
  const [abierta, setAbierta] = useState(null)

  const anios = useMemo(() => [...new Set(pagos.map((p) => p.creada.slice(0, 4)))].sort().reverse(), [pagos])
  const lista = useMemo(() => {
    const t = q.toLowerCase()
    return pagos.filter((p) =>
      (!estado || p.estado === estado) && (!anio || p.creada.startsWith(anio)) &&
      (!t || [p.titulo, p.correlativo, p.solicitante, p.atributos['Nombre de solicitante'], p.aprobador, p.departamento, p.webdox_id, p.certificado?.numero].join(' ').toLowerCase().includes(t)))
  }, [pagos, q, estado, anio])
  const total = lista.reduce((s, p) => s + (p.certificado?.total ?? 0), 0)

  return (
    <>
      <div className="flex flex-wrap items-end gap-2.5">
        <Filtro id="pq" label="Buscar">
          <input id="pq" type="search" className="inp" placeholder="Correlativo, nombre, ID…" value={q} onChange={(e) => setQ(e.target.value)} />
        </Filtro>
        <Filtro id="pa" label="Año">
          <select id="pa" className="inp" value={anio} onChange={(e) => setAnio(e.target.value)}>
            <option value="">Todos</option>
            {anios.map((a) => <option key={a}>{a}</option>)}
          </select>
        </Filtro>
        <Filtro id="pe" label="Estado">
          <select id="pe" className="inp" value={estado} onChange={(e) => setEstado(e.target.value)}>
            <option value="">Todos</option>
            {Object.entries(estados).map(([k, v]) => <option key={k} value={k}>{v}</option>)}
          </select>
        </Filtro>
      </div>

      <div className="max-h-[calc(100vh-290px)] min-h-[200px] overflow-auto rounded-lg border border-line bg-surface">
        <table className="w-full min-w-[1400px] border-separate border-spacing-0 text-[13px]">
          <thead>
            <tr>
              <th className={th}>ID Webdox</th>
              <th className={th}>Estado</th>
              <th className={th}>Creada</th>
              <th className={th}>Correlativo</th>
              <th className={th}>Fecha certificado</th>
              <th className={th}>Solicitante</th>
              <th className={th}>Aprobador(a)</th>
              <th className={th}>Departamento</th>
              <th className={th + ' text-right'}>Documentos</th>
              <th className={th}>Fecha de pago</th>
              <th className={th}>Certificado</th>
              <th className={th + ' text-right'}>Total certificado</th>
              <th className={th}>Antecedentes</th>
              <th className={th}>Detalle</th>
            </tr>
          </thead>
          <tbody>
            {lista.length === 0 && (
              <tr><td colSpan={14} className="p-8 text-center text-ink2">
                {pagos.length ? 'No hay pagos con estos filtros.' : 'Todavía no hay pagos. Pulse «Sincronizar con Webdox».'}
              </td></tr>
            )}
            {lista.map((p) => (
              <Fragment key={p.id}>
                <tr className="cursor-pointer hover:[&>td]:bg-surface2" onClick={() => setAbierta(abierta === p.id ? null : p.id)}>
                  <td className={td + ' whitespace-nowrap font-mono font-semibold'}>ID{p.webdox_id}</td>
                  <td className={td}><span className={PILL[p.estado] ?? 'pill pill-warn'}>{estados[p.estado] ?? p.estado}</span></td>
                  <td className={td + ' whitespace-nowrap font-mono text-xs'}>{fecha(p.creada)}</td>
                  <td className={td + ' text-center'}>{p.correlativo ?? '—'}</td>
                  <td className={td + ' whitespace-nowrap font-mono text-xs'}>{fecha(p.fecha_certificado)}</td>
                  <td className={td}>{p.atributos['Nombre de solicitante'] ?? p.solicitante ?? '—'}</td>
                  <td className={td}>
                    {p.aprobador ?? '—'}
                    {p.atributos['Cargo de aprobador de pago'] && <div className="text-[11px] text-ink3">{p.atributos['Cargo de aprobador de pago']}</div>}
                  </td>
                  <td className={td}>{p.departamento ?? '—'}</td>
                  <td className={td + num}>{p.cantidad_documentos ?? '—'}</td>
                  <td className={td + ' text-xs'}>{p.fecha_pago_requerida ?? '—'}</td>
                  <td className={td + ' text-xs'}>
                    {p.certificado
                      ? <span className="font-mono">N° {p.certificado.numero ?? '—'} · {p.certificado.lineas} facturas</span>
                      : <span className="text-ink3">Sin Excel</span>}
                  </td>
                  <td className={td + num}>{p.certificado ? fmt(p.certificado.total) : '—'}</td>
                  <td className={td + ' text-xs'}>
                    {p.link_antecedentes
                      ? <a href={p.link_antecedentes} target="_blank" rel="noreferrer" className="text-accent hover:underline" onClick={(e) => e.stopPropagation()}>Abrir carpeta ↗</a>
                      : '—'}
                  </td>
                  <td className={td + ' whitespace-nowrap text-xs'}>
                    <Link href={`/pagos/${p.webdox_id}`} className="text-accent underline" onClick={(e) => e.stopPropagation()}>
                      {p.certificado ? 'Ver certificado' : 'Ver solicitud'}
                    </Link>
                  </td>
                </tr>
                {abierta === p.id && (
                  <tr>
                    <td colSpan={14} className={td + ' bg-surface2'}>
                      <div className="mb-1 text-xs font-semibold">{p.titulo}</div>
                      <dl className="m-0 grid gap-x-6 gap-y-1 text-xs sm:grid-cols-2">
                        {Object.entries(p.atributos).map(([k, v]) => (
                          <div key={k} className="flex gap-2">
                            <dt className="shrink-0 text-ink3">{k}:</dt>
                            <dd className="m-0 break-words">{v === null || v === '' ? '—' : typeof v === 'object' ? JSON.stringify(v) : String(v)}</dd>
                          </div>
                        ))}
                      </dl>
                    </td>
                  </tr>
                )}
              </Fragment>
            ))}
          </tbody>
          {lista.length > 0 && (
            <tfoot>
              <tr className="font-semibold">
                <td colSpan={11} className={pie}>
                  Total ({lista.length} {lista.length === 1 ? 'solicitud' : 'solicitudes'}{lista.length < pagos.length ? ' filtradas' : ''})
                </td>
                <td className={pie + num}>{fmt(total)}</td>
                <td className={pie} colSpan={2} />
              </tr>
            </tfoot>
          )}
        </table>
      </div>
    </>
  )
}

export default function Pagos({ pagos, lineas, estados, sincronizado, origen }) {
  const [vista, setVista] = useState('solicitudes')
  const [cargando, setCargando] = useState(null)
  const accion = (url, cual) => router.post(url, {}, {
    preserveScroll: true, onStart: () => setCargando(cual), onFinish: () => setCargando(null),
  })
  const pestana = (v) => 'btn btn-sm ' + (vista === v ? 'border-accent text-accent' : '')

  return (
    <section className="flex flex-col gap-3">
      <div className="flex flex-wrap items-center gap-2">
        <button className={pestana('solicitudes')} onClick={() => setVista('solicitudes')}>Solicitudes Webdox ({pagos.length})</button>
        <button className={pestana('proveedores')} onClick={() => setVista('proveedores')}>Proveedores pagados ({lineas.length})</button>
        <div className="flex-1" />
        <button className="btn" onClick={() => accion('/pagos/certificados', 'cert')} disabled={!!cargando}>
          {cargando === 'cert' ? 'Leyendo…' : 'Leer certificados'}
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

      {vista === 'proveedores' ? <Proveedores lineas={lineas} /> : <Solicitudes pagos={pagos} estados={estados} />}

      <p className="m-0 text-xs text-ink3">
        Solicitudes «{origen.formulario}» de {origen.unidad} desde Webdox; facturas desde los Excel «ID####_Certificado de Pago.xlsx»
        de la carpeta compartida. El ID de Webdox une cada certificado con su solicitud. «Leer certificados» solo lee los nuevos o modificados.
      </p>
    </section>
  )
}

Pagos.layout = (page) => <AppLayout>{page}</AppLayout>
