import { Link } from '@inertiajs/react'
import AppLayout from '../Layouts/AppLayout.jsx'
import { fmt } from '../lib/data.js'

const PILL = { finished: 'pill pill-ok', canceled: 'pill pill-bad', rejected: 'pill pill-bad' }
const th = 'sticky top-0 z-[2] whitespace-nowrap border-b border-lineStrong bg-surface2 px-2 py-2 text-left text-[10.5px] font-semibold uppercase tracking-wide text-ink2'
const td = 'border-b border-line px-2 py-1.5 align-top'
const pie = 'border-t border-lineStrong bg-surface2 px-2 py-2 font-semibold'
const num = ' tnum whitespace-nowrap text-right font-mono'
const fecha = (s) => (s ? s.split('-').reverse().join('-') : '—')

function Dato({ label, children }) {
  return (
    <div className="flex flex-col">
      <span className="lbl">{label}</span>
      <span className="font-medium">{children ?? '—'}</span>
    </div>
  )
}

/** Detalle de una solicitud «Pago a proveedores»: campos de Webdox y facturas de su certificado de pago. */
export default function PagoDetalle({ solicitud: s, estados, certificado: c }) {
  const a = s.atributos
  const suma = (k) => (c ? c.lineas.reduce((t, l) => t + (l[k] ?? 0), 0) : 0)

  return (
    <section className="flex flex-col gap-4">
      <div className="flex flex-wrap items-start gap-3">
        <div className="min-w-0 flex-1">
          <Link href="/pagos" className="text-sm text-accent underline">← Pagos a proveedores</Link>
          <h1 className="m-0 mt-1 text-xl font-semibold">
            Pago a proveedores{c?.numero ? ` · Certificado N° ${c.numero}` : s.correlativo ? ` · Correlativo ${s.correlativo}` : ''}
          </h1>
          <p className="m-0 text-sm text-ink2">
            ID Webdox <b className="font-mono">{s.webdox_id}</b> · <span className={PILL[s.estado] ?? 'pill pill-warn'}>{estados[s.estado] ?? s.estado}</span>
          </p>
        </div>
        <div className="flex flex-wrap gap-2">
          {s.link_antecedentes && <a className="btn" href={s.link_antecedentes} target="_blank" rel="noreferrer">Carpeta de antecedentes ↗</a>}
          {c?.descargable && <a className="btn btn-primary" href={`/pagos/${s.webdox_id}/excel`}>Descargar Excel</a>}
        </div>
      </div>

      <div className="panel grid grid-cols-2 gap-x-6 gap-y-3 md:grid-cols-4">
        <Dato label="Total certificado"><span className="tnum font-mono text-lg">{c ? fmt(suma('total')) : '—'}</span></Dato>
        <Dato label="Correlativo certificado">{s.correlativo}</Dato>
        <Dato label="Fecha certificado">{fecha(c?.fecha ?? s.fecha_certificado)}</Dato>
        <Dato label="Creada en Webdox">{fecha(s.creada)}</Dato>
        <Dato label="Departamento">{s.departamento}</Dato>
        <Dato label="Solicitante">{a['Nombre de solicitante'] ?? s.solicitante}</Dato>
        <Dato label="Aprobador(a)">{s.aprobador}{a['Cargo de aprobador de pago'] ? ` (${a['Cargo de aprobador de pago']})` : ''}</Dato>
        <Dato label="Documentos a pagar">{s.cantidad_documentos}{c ? ` · ${c.lineas.length} en el certificado` : ''}</Dato>
        <Dato label="Fecha de pago requerida">{s.fecha_pago_requerida}</Dato>
      </div>

      <details className="panel">
        <summary className="cursor-pointer font-semibold">Todos los campos de Webdox</summary>
        <div className="mt-2 grid gap-x-6 gap-y-1 text-sm sm:grid-cols-2">
          <div><span className="text-ink2">Título:</span> {s.titulo}</div>
          {Object.entries(a).map(([k, v]) => (
            <div key={k} className="break-words"><span className="text-ink2">{k}:</span> {v === null || v === '' ? '—' : typeof v === 'object' ? JSON.stringify(v) : String(v)}</div>
          ))}
          <div><span className="text-ink2">Adjuntos en Webdox:</span> {s.adjuntos.length ? s.adjuntos.map((x) => x.nombre).join(' · ') : '—'}</div>
        </div>
      </details>

      <div className="panel overflow-auto !p-0">
        <div className="flex flex-wrap items-baseline gap-2 border-b border-line px-4 py-3">
          <h2 className="m-0 text-sm font-semibold">Facturas del certificado de pago</h2>
          {c && <span className="text-xs text-ink3">{c.archivo}</span>}
        </div>
        {!c ? (
          <p className="m-0 px-4 py-3 text-sm text-ink2">
            No hay Excel «ID{s.webdox_id}_Certificado de Pago.xlsx» en la carpeta compartida. En 2024 y 2025 solo están los certificados firmados en PDF.
          </p>
        ) : (
          <table className="w-full min-w-[1100px] border-collapse text-xs">
            <thead><tr>
              {['N°', 'Proveedor', 'RUT', 'Factura / NC'].map((h) => <th key={h} className={th}>{h}</th>)}
              {['Neto', 'IVA', 'Exento', 'Total'].map((h) => <th key={h} className={th + ' text-right'}>{h}</th>)}
              {['Cuenta', 'Unidad', 'Centro de costo', 'Observación'].map((h) => <th key={h} className={th}>{h}</th>)}
            </tr></thead>
            <tbody>
              {c.lineas.map((l) => (
                <tr key={l.id} className="hover:bg-surface2">
                  <td className={td}>{l.n}</td>
                  <td className={td + ' font-medium'}>{l.proveedor}</td>
                  <td className={td + ' whitespace-nowrap font-mono'}>{l.rut}</td>
                  <td className={td + ' font-mono'}>{l.factura}</td>
                  <td className={td + num}>{fmt(l.neto)}</td>
                  <td className={td + num}>{fmt(l.iva)}</td>
                  <td className={td + num}>{l.exento ? fmt(l.exento) : ''}</td>
                  <td className={td + num + ' font-semibold'}>{fmt(l.total)}</td>
                  <td className={td + ' font-mono'}>{l.cuenta}</td>
                  <td className={td}>{l.unidad}</td>
                  <td className={td + ' font-mono'}>{l.centro_costo}</td>
                  <td className={td + ' text-ink2'}>{l.obs}</td>
                </tr>
              ))}
            </tbody>
            <tfoot><tr>
              <td className={pie} colSpan={4}>Total ({c.lineas.length} documentos)</td>
              <td className={pie + num}>{fmt(suma('neto'))}</td>
              <td className={pie + num}>{fmt(suma('iva'))}</td>
              <td className={pie + num}>{fmt(suma('exento'))}</td>
              <td className={pie + num}>{fmt(suma('total'))}</td>
              <td className={pie} colSpan={4} />
            </tr></tfoot>
          </table>
        )}
      </div>
    </section>
  )
}

PagoDetalle.layout = (page) => <AppLayout>{page}</AppLayout>
