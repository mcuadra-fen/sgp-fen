import { Link } from '@inertiajs/react'
import AppLayout from '../Layouts/AppLayout.jsx'
import { fmt } from '../lib/data.js'

const PILL = { finished: 'pill pill-ok', canceled: 'pill pill-bad', rejected: 'pill pill-bad' }
const th = 'sticky top-0 z-[2] border-b border-lineStrong bg-surface2 px-2 py-2 text-left align-bottom text-[10.5px] font-semibold uppercase tracking-wide text-ink2'
const td = 'border-b border-line px-2 py-1.5 align-top'
const num = ' tnum whitespace-nowrap text-right font-mono'
const fecha = (s) => (s ? s.split('-').reverse().join('-') : '—')
const cc = (c) => (c && String(c).length === 15 ? `${String(c).slice(0, 8)}.${String(c).slice(8, 12)}.${String(c).slice(12)}` : c)
// «TRASPASO SERVICIOS SOPORTE COMPUTACIONAL UDT» → «Servicios soporte computacional»
const columna = (h) => {
  const t = h.replace(/^TRASPASO\s+/i, '').replace(/\s+UDT$/i, '').trim()
  return t === t.toUpperCase() ? t.charAt(0) + t.slice(1).toLowerCase() : t
}

function Dato({ label, children }) {
  return (
    <div className="flex flex-col">
      <span className="lbl">{label}</span>
      <span className="font-medium">{children ?? '—'}</span>
    </div>
  )
}

/** Detalle de una solicitud «Traspaso (Cobros DEA)»: campos de Webdox y resumen del Excel de la carpeta compartida. */
export default function TraspasoDetalle({ solicitud: s, estados, excel, resumen, error, comprobantes }) {
  // Primeras columnas del Excel: área, subárea y CECO; el resto son montos
  const iCeco = resumen ? resumen.encabezados.findIndex((h) => h.toUpperCase() === 'CECO') : -1
  const montos = resumen ? resumen.encabezados.map((_, i) => i).filter((i) => i > iCeco) : []
  // Solo las filas con algún monto (el tarifario lista todas las áreas, cobren o no)
  const filas = resumen ? resumen.filas.filter((f) => montos.some((i) => Number(f[i]))) : []
  const totalExcel = resumen?.total ? resumen.total[montos[montos.length - 1]] : null

  return (
    <section className="flex flex-col gap-4">
      <div className="flex flex-wrap items-start gap-3">
        <div className="min-w-0 flex-1">
          <Link href="/traspasos" className="text-sm text-accent underline">← Cobros a áreas</Link>
          <h1 className="m-0 mt-1 text-xl font-semibold">{s.descripcion ?? s.titulo}</h1>
          <p className="m-0 text-sm text-ink2">
            ID Webdox <b className="font-mono">{s.webdox_id}</b> · <span className={PILL[s.estado] ?? 'pill'}>{estados[s.estado] ?? s.estado}</span>
          </p>
        </div>
        {excel && <a className="btn btn-primary" href={`/traspasos/${s.webdox_id}/excel`}>Descargar Excel</a>}
      </div>

      <div className="panel grid grid-cols-2 gap-x-6 gap-y-3 md:grid-cols-4">
        <Dato label="Total de traspaso"><span className="tnum font-mono text-lg">{s.total !== null ? fmt(s.total) : '—'}</span></Dato>
        <Dato label="Correlativo memo">{s.correlativo}</Dato>
        <Dato label="Fecha de memo">{fecha(s.fecha_memo)}</Dato>
        <Dato label="Creada en Webdox">{fecha(s.creada)}</Dato>
        <Dato label="Unidad">{s.unidad}</Dato>
        <Dato label="Solicitante">{s.solicitante_nombre ?? s.solicitante}</Dato>
        <Dato label="Aprobador(a)">{s.aprobador}{s.cargo_aprobador ? ` (${s.cargo_aprobador})` : ''}</Dato>
        <Dato label="CECO ingreso"><span className="font-mono text-sm">{cc(s.ceco_ingreso)}</span></Dato>
      </div>

      <details className="panel">
        <summary className="cursor-pointer font-semibold">Todos los campos de Webdox</summary>
        <div className="mt-2 grid gap-x-6 gap-y-1 text-sm sm:grid-cols-2">
          <div><span className="text-ink2">Título:</span> {s.titulo}</div>
          {Object.entries(s.atributos).filter(([, v]) => typeof v !== 'object').map(([k, v]) => (
            <div key={k}><span className="text-ink2">{k}:</span> {String(v ?? '—')}</div>
          ))}
          <div><span className="text-ink2">Adjuntos en Webdox:</span> {s.adjuntos.length ? s.adjuntos.map((a) => a.nombre).join(' · ') : '—'}</div>
        </div>
      </details>

      <div className="panel overflow-auto !p-0">
        <div className="flex flex-wrap items-baseline gap-2 border-b border-line px-4 py-3">
          <h2 className="m-0 text-sm font-semibold">Resumen del Excel de detalle</h2>
          {excel && <span className="text-xs text-ink3">{excel}</span>}
          {resumen?.titulos?.length > 0 && <span className="text-xs text-ink2">· {resumen.titulos.filter((t) => /PRORRATEO|MES/i.test(t)).join(' · ')}</span>}
        </div>
        {error && <p className="m-0 px-4 py-3 text-sm text-bad">{error}</p>}
        {!excel && (
          <p className="m-0 px-4 py-3 text-sm text-ink2">
            El Excel de esta solicitud no está en la carpeta compartida
            {s.adjuntos.some((a) => /xlsx?$/i.test(a.tipo ?? '')) ? ' (sí está adjunto en Webdox: ábralo desde allí)' : ''}.
            La carpeta tiene los traspasos desde abril de 2026.
          </p>
        )}
        {resumen && (
          <table className="w-full min-w-[1100px] border-collapse text-xs">
            <thead><tr>
              <th className={th}>Área</th><th className={th}>Subárea</th><th className={th}>CECO</th>
              {montos.map((i) => <th key={i} className={th + ' text-right'}>{columna(resumen.encabezados[i])}</th>)}
            </tr></thead>
            <tbody>
              {filas.map((f, n) => (
                <tr key={n} className="hover:bg-surface2">
                  <td className={td + ' text-ink2'}>{n === 0 || filas[n - 1][0] !== f[0] ? f[0] : ''}</td>
                  <td className={td}>{f[1]}</td>
                  <td className={td + ' whitespace-nowrap font-mono'}>{cc(f[iCeco])}</td>
                  {montos.map((i, k) => (
                    <td key={i} className={td + num + (k === montos.length - 1 ? ' font-semibold' : '')}>{Number(f[i]) ? fmt(f[i]) : ''}</td>
                  ))}
                </tr>
              ))}
            </tbody>
            {resumen.total && (
              <tfoot><tr>
                <td className="border-t border-lineStrong bg-surface2 px-2 py-2 font-semibold" colSpan={3}>Total ({filas.length} centros de costo)</td>
                {montos.map((i) => <td key={i} className={'border-t border-lineStrong bg-surface2 px-2 py-2 font-semibold' + num}>{fmt(resumen.total[i] ?? 0)}</td>)}
              </tr></tfoot>
            )}
          </table>
        )}
        {resumen && s.total !== null && totalExcel !== null && totalExcel !== s.total && (
          <p className="m-0 px-4 py-2 text-xs text-warn">El total del Excel ({fmt(totalExcel)}) no coincide con el «Total de traspaso» de Webdox ({fmt(s.total)}).</p>
        )}
      </div>

      {comprobantes.length > 0 && (
        <div className="panel overflow-auto !p-0">
          <h2 className="m-0 border-b border-line px-4 py-3 text-sm font-semibold">Comprobantes contables ({comprobantes.length})</h2>
          <table className="w-full border-collapse text-sm">
            <tbody>
              {comprobantes.map((c, n) => (
                <tr key={n}>
                  <td className={td + ' whitespace-nowrap'}>{fecha(c.fecha)}</td>
                  <td className={td + ' font-mono text-xs'}>{c.comprobante ?? 'asiento .txt'}</td>
                  <td className={td}>{c.glosa}</td>
                  <td className={td + num}>{fmt(c.total)}</td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}
    </section>
  )
}

TraspasoDetalle.layout = (page) => <AppLayout>{page}</AppLayout>
