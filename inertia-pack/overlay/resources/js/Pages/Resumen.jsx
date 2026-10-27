import AppLayout from '../Layouts/AppLayout.jsx'
import BarChart from '../components/BarChart.jsx'
import HBars from '../components/HBars.jsx'
import { fmt } from '../lib/data.js'

function Kpi({ label, value, hint }) {
  return (
    <div className="panel !px-4 !py-3.5">
      <small className="text-xs text-ink2">{label}</small>
      <div className="tnum mt-0.5 break-words font-mono text-[22px] font-medium">{value}</div>
      <div className="text-xs text-ink3">{hint}</div>
    </div>
  )
}

const PILL = { warn: 'pill pill-warn', bad: 'pill pill-bad', ok: 'pill pill-ok' }

export default function Resumen({ meses, total, cantidad, ejemplos, mayorMes, mayorMonto, porSigma, porUnidad, alertas }) {
  return (
    <section>
      <div className="grid grid-cols-[repeat(auto-fit,minmax(200px,1fr))] gap-3">
        <Kpi label="Monto total anual" value={fmt(total)} hint="CLP, suma de las 12 columnas" />
        <Kpi label="Partidas" value={cantidad} hint={ejemplos ? `${ejemplos} son filas de ejemplo` : 'todas con datos propios'} />
        <Kpi label="Promedio mensual" value={fmt(total / 12)} hint="CLP por mes" />
        <Kpi label="Mes de mayor monto" value={mayorMes ?? '—'} hint={mayorMes ? `${fmt(mayorMonto)} CLP` : ''} />
      </div>
      <div className="mt-3 grid gap-3 md:grid-cols-[minmax(0,1.5fr)_minmax(0,1fr)]">
        <div className="panel"><h2 className="panel-title">Monto por mes (CLP)</h2><BarChart values={meses} /></div>
        <div className="panel"><h2 className="panel-title">Por clasificación SIGMA</h2><HBars data={porSigma} grand={total} /></div>
      </div>
      <div className="mt-3 grid gap-3 md:grid-cols-[minmax(0,1.5fr)_minmax(0,1fr)]">
        <div className="panel"><h2 className="panel-title">Por unidad</h2><HBars data={porUnidad} grand={total} /></div>
        <div className="panel">
          <h2 className="panel-title">Revisión de datos</h2>
          <ul className="m-0 flex list-none flex-col gap-2 p-0">
            {alertas.map(([tipo, etiqueta, mensaje]) => (
              <li key={mensaje} className="flex items-start gap-2.5 text-[13px]">
                <span className={PILL[tipo]}>{etiqueta}</span><span>{mensaje}</span>
              </li>
            ))}
          </ul>
        </div>
      </div>
    </section>
  )
}

Resumen.layout = (page) => <AppLayout>{page}</AppLayout>
