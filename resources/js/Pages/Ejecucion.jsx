import { Fragment, useState } from 'react'
import { usePage } from '@inertiajs/react'
import AppLayout from '../Layouts/AppLayout.jsx'
import { fmt } from '../lib/data.js'

const th = 'sticky top-0 z-[2] h-10 whitespace-nowrap border-b border-lineStrong bg-surface2 px-2.5 text-left text-[11px] font-semibold uppercase tracking-wider text-ink2'
const td = 'border-b border-line px-2.5 py-2 align-top'
const pie = 'sticky bottom-0 border-t border-lineStrong bg-surface2 px-2.5 py-2.5 font-semibold'
const num = ' tnum whitespace-nowrap text-right font-mono'
const fecha = (s) => (s ? s.split('-').reverse().join('-') : '—')
const ORIGEN = { prorrateo: 'Prorrateo', 'otra-unidad': 'Otra unidad' }
const MOTIVOS = {
  varios: ['Cuenta «VARIOS»', 'El reparto por cuenta está en otra hoja del certificado.'],
  'sin-cuenta': ['Cuenta sin presupuesto', 'Ninguna partida tiene esa cuenta contable.'],
}

function Facturas({ lista, motivo }) {
  return (
    <table className="w-full border-collapse text-xs">
      <thead>
        <tr>{['ID Webdox', 'Certificado', 'Fecha', 'Proveedor', 'Factura', 'Cuenta', 'Centro de costo', 'Origen', 'Detalle', 'Total'].map((h) => (
          <th key={h} className="border-b border-line px-2 py-1 text-left font-semibold text-ink2">{h}</th>))}
        </tr>
      </thead>
      <tbody>
        {lista.map((f) => (
          <tr key={f.id}>
            <td className="px-2 py-1 font-mono">ID{f.webdox_id}</td>
            <td className="px-2 py-1">{f.numero}</td>
            <td className="px-2 py-1 whitespace-nowrap">{fecha(f.fecha)}</td>
            <td className="px-2 py-1">{f.proveedor}</td>
            <td className="px-2 py-1">{f.factura}</td>
            <td className="px-2 py-1 font-mono">{f.cuenta}</td>
            <td className="px-2 py-1 font-mono">{f.centro_costo}</td>
            <td className="px-2 py-1 whitespace-nowrap">
              {motivo ? <span className="pill" title={MOTIVOS[f.motivo][1]}>{MOTIVOS[f.motivo][0]}</span>
                : f.unidad ?? <span className="pill">{ORIGEN[f.origen]}</span>}
            </td>
            <td className="px-2 py-1 text-ink2">{f.obs}</td>
            <td className={'px-2 py-1' + num}>{fmt(f.total)}</td>
          </tr>
        ))}
      </tbody>
    </table>
  )
}

export default function Ejecucion({ filas, sinPartida }) {
  const { anio, version, versiones } = usePage().props
  const [abierta, setAbierta] = useState(null)
  const [soloPagos, setSoloPagos] = useState(true)

  const lista = filas.filter((f) => !soloPagos || f.facturas.length)
  const sum = (xs, k) => xs.reduce((a, x) => a + (typeof k === 'function' ? k(x) : x[k]), 0)
  const total = (f) => f.pagado + f.pagado_otros
  // Los totales consideran todo el presupuesto, aunque la tabla muestre solo las cuentas con pagos
  const [ppto, pagUdt, pagOtros, fuera] = [sum(filas, 'presupuesto'), sum(filas, 'pagado'), sum(filas, 'pagado_otros'), sum(sinPartida, 'total')]
  const sinPagos = !filas.some((f) => f.facturas.length) && !sinPartida.length
  const pct = (p, g) => (p ? Math.round((g / p) * 100) : null)

  return (
    <section className="flex flex-col gap-3">
      <div className="grid grid-cols-2 gap-3 md:grid-cols-4">
        {[['Presupuesto', ppto], ['Pagado', pagUdt + pagOtros], ['Saldo', ppto - pagUdt - pagOtros], ['Pagado sin cuenta presupuestada', fuera]].map(([t, v]) => (
          <div key={t} className="card p-3">
            <div className="lbl">{t}</div>
            <div className={'tnum mt-1 font-mono text-lg font-semibold ' + (v < 0 ? 'text-bad' : '')}>{fmt(v)}</div>
          </div>
        ))}
      </div>

      <div className="flex flex-wrap items-end gap-3">
        <label className="flex items-center gap-2 text-sm">
          <input type="checkbox" checked={soloPagos} onChange={(e) => setSoloPagos(e.target.checked)} /> Solo cuentas con pagos
        </label>
        <span className="text-xs text-ink3">{versiones[version]} {anio} vs. certificados de pago con fecha {anio}</span>
      </div>

      <div className="card overflow-auto">
        <table className="w-full min-w-[980px] border-collapse text-sm">
          <thead>
            <tr>
              <th className={th}>Cuenta</th>
              <th className={th + ' text-right'}>Presupuesto</th>
              <th className={th + ' text-right'} title="Centro de costo de Gabinete UDT o Unidad de Servicios UDT">Pagado C.C. UDT</th>
              <th className={th + ' text-right'} title="Centro de costo de otra unidad, o «PRORRATEO ADJUNTO»">Pagado otros C.C.</th>
              <th className={th + ' text-right'}>Total pagado</th>
              <th className={th + ' text-right'}>Saldo</th><th className={th + ' text-right'}>% ejecutado</th>
              <th className={th + ' text-right'}>Facturas</th>
            </tr>
          </thead>
          <tbody>
            {lista.map((f) => {
              const p = pct(f.presupuesto, total(f))
              const exceso = total(f) > f.presupuesto
              return (
                <Fragment key={f.clave}>
                  <tr className={'cursor-pointer hover:bg-surface2 ' + (abierta === f.clave ? 'bg-surface2' : '')}
                    onClick={() => f.facturas.length && setAbierta(abierta === f.clave ? null : f.clave)}>
                    <td className={td}>
                      {f.cuenta}
                      <div className="text-[11px] text-ink3">{Object.entries(f.por_unidad).map(([u, v]) => `${u}: ${fmt(v)}`).join(' · ')}</div>
                    </td>
                    <td className={td + num}>{fmt(f.presupuesto)}</td>
                    <td className={td + num}>{fmt(f.pagado)}</td>
                    <td className={td + num + ' text-ink2'}>{f.pagado_otros ? fmt(f.pagado_otros) : '—'}</td>
                    <td className={td + num + ' font-semibold'}>{fmt(total(f))}</td>
                    <td className={td + num + (exceso ? ' text-bad' : '')}>{fmt(f.presupuesto - total(f))}</td>
                    <td className={td + num}>
                      {p === null ? <span className="pill pill-bad">sin presupuesto</span>
                        : <span className={exceso ? 'pill pill-bad' : p >= 80 ? 'pill' : 'pill pill-ok'}>{p}%</span>}
                    </td>
                    <td className={td + num}>{f.facturas.length ? `${f.facturas.length} ${abierta === f.clave ? '▲' : '▼'}` : '—'}</td>
                  </tr>
                  {abierta === f.clave && (
                    <tr><td colSpan={8} className="border-b border-line bg-surface2 px-4 py-2"><Facturas lista={f.facturas} /></td></tr>
                  )}
                </Fragment>
              )
            })}
            {!lista.length && (
              <tr><td colSpan={8} className={td + ' text-center text-ink3'}>
                {sinPagos ? `No hay certificados de pago con fecha ${anio}. Elija otro año arriba o desmarque «Solo cuentas con pagos» para ver el presupuesto.`
                  : 'No hay cuentas para mostrar.'}
              </td></tr>
            )}
          </tbody>
          <tfoot>
            <tr>
              <td className={pie}>Total</td>
              <td className={pie + num}>{fmt(ppto)}</td><td className={pie + num}>{fmt(pagUdt)}</td><td className={pie + num}>{fmt(pagOtros)}</td>
              <td className={pie + num}>{fmt(pagUdt + pagOtros)}</td><td className={pie + num}>{fmt(ppto - pagUdt - pagOtros)}</td>
              <td className={pie + num}>{pct(ppto, pagUdt + pagOtros) ?? '—'}%</td><td className={pie} />
            </tr>
          </tfoot>
        </table>
      </div>

      {sinPartida.length > 0 && (
        <div className="card overflow-auto p-3">
          <h3 className="m-0 mb-1 text-sm font-semibold">Pagos sin cuenta en el presupuesto ({sinPartida.length} facturas · {fmt(fuera)})</h3>
          <p className="m-0 mb-2 text-xs text-ink3">
            {Object.entries(MOTIVOS).map(([k, [t, d]]) => {
              const xs = sinPartida.filter((f) => f.motivo === k)
              return xs.length ? <span key={k} className="mr-3"><b>{t}</b> ({xs.length} · {fmt(sum(xs, 'total'))}): {d}</span> : null
            })}
          </p>
          <Facturas lista={sinPartida} motivo />
        </div>
      )}

      <p className="m-0 text-xs text-ink3">
        Cruce por cuenta contable: la cuenta de la factura es el número con que empieza la cuenta de la partida. El centro de costo indica
        de dónde salió el pago (sus 8 primeros dígitos son el código de unidad). Haga clic en una fila para ver sus facturas.
      </p>
    </section>
  )
}

Ejecucion.layout = (page) => <AppLayout>{page}</AppLayout>
