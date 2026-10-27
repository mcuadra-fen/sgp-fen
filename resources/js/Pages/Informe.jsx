import { useState } from 'react'
import { router, usePage } from '@inertiajs/react'
import AppLayout from '../Layouts/AppLayout.jsx'
import { fmt } from '../lib/data.js'

const pct = (p) => (p === null ? 'n/a' : p.toLocaleString('es-CL', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + '%')

// Mismo estilo que la grilla de Presupuesto: totales en gris claro, flujos netos destacados
const ESTILO = {
  total: { fila: 'bg-surface2 font-semibold' },
  item: { fila: '', nombre: '!pl-6 text-ink2' },
  neto: { fila: 'bg-accentSoft font-semibold' },
}
const th = 'h-10 whitespace-nowrap border-b border-lineStrong bg-surface2 px-3 text-[11px] font-semibold uppercase tracking-wider text-ink2'
const celda = 'border-b border-line px-3 py-2.5'
const num = ' text-right font-mono'

export default function Informe({ a, b, filas }) {
  const { anios } = usePage().props
  const [titulo, setTitulo] = useState(`Presupuesto ${b} vs Proyección Cierre ${a}`)
  const [colA, setColA] = useState(`PROY. CIERRE ${a}`)
  const [colB, setColB] = useState(`PRESUPUESTO ${b}`)
  const opciones = [...new Set([...anios, a, b])].sort((x, y) => y - x)

  // Al cambiar los años se vuelven a proponer los títulos
  const ir = (na, nb) => {
    setTitulo(`Presupuesto ${nb} vs Proyección Cierre ${na}`)
    setColA(`PROY. CIERRE ${na}`)
    setColB(`PRESUPUESTO ${nb}`)
    router.get('/informe', { a: na, b: nb }, { preserveState: true, preserveScroll: true })
  }

  const descargar = async () => {
    const XLSX = await import('xlsx')
    const aoa = [
      [titulo], [],
      ['ITEMS', colA, colB, `VAR ${colB} vs ${colA}`, '%'],
      ...filas.map((f) => (f.tipo === 'separador' ? [] : [f.nombre, f.a, f.b, f.var, f.pct === null ? 'n/a' : f.pct / 100])),
    ]
    const ws = XLSX.utils.aoa_to_sheet(aoa)
    ws['!cols'] = [{ wch: 40 }, { wch: 20 }, { wch: 20 }, { wch: 26 }, { wch: 10 }]
    // Formato de miles y porcentaje en las celdas numéricas
    aoa.forEach((fila, r) => fila.forEach((v, c) => {
      const cell = ws[XLSX.utils.encode_cell({ r, c })]
      if (r > 2 && cell && typeof v === 'number') cell.z = c === 4 ? '0.00%' : '#,##0'
    }))
    const wb = XLSX.utils.book_new()
    XLSX.utils.book_append_sheet(wb, ws, 'Informe')
    XLSX.writeFile(wb, `informe-${b}-vs-${a}.xlsx`)
  }

  const titulos = 'w-full rounded border border-transparent bg-transparent text-inherit hover:border-lineStrong focus:border-accent focus:outline-none'

  return (
    <section className="flex flex-col gap-3">
      <div className="no-print panel flex flex-wrap items-end gap-3">
        <label className="flex flex-col gap-1">
          <span className="lbl">Año base</span>
          <select className="inp !w-auto" value={a} onChange={(e) => ir(Number(e.target.value), b)}>
            {opciones.map((x) => <option key={x} value={x}>{x}</option>)}
          </select>
        </label>
        <span className="pb-2 text-ink3">comparado con</span>
        <label className="flex flex-col gap-1">
          <span className="lbl">Año</span>
          <select className="inp !w-auto" value={b} onChange={(e) => ir(a, Number(e.target.value))}>
            {opciones.map((x) => <option key={x} value={x}>{x}</option>)}
          </select>
        </label>
        <div className="flex-1" />
        <button className="btn" onClick={() => window.print()}>Imprimir / PDF</button>
        <button className="btn btn-primary" onClick={descargar}>Descargar Excel</button>
        <p className="m-0 w-full text-xs text-ink3">
          El título y los nombres de las columnas se pueden editar haciendo clic sobre ellos. Variación = {b} − {a};
          % = variación ÷ {a} («n/a» si {a} es cero o el monto cambia de signo). Ingresos en positivo, gastos en negativo.
        </p>
      </div>

      <div className="informe panel flex flex-col gap-3">
        <input
          className={titulos + ' panel-title !mb-0 px-1 text-base print:hidden'}
          value={titulo}
          onChange={(e) => setTitulo(e.target.value)}
          aria-label="Título del informe"
        />
        <h1 className="panel-title !mb-0 hidden text-base print:block">{titulo}</h1>
        <div className="overflow-x-auto rounded-lg border border-line">
          <table className="tnum w-full min-w-[760px] border-separate border-spacing-0">
            <thead>
              <tr>
                <th className={th + ' w-[34%] text-left'}>Items</th>
                {[[colA, setColA], [colB, setColB]].map(([v, set], i) => (
                  <th key={i} className={th + ' text-right'}>
                    <input className={titulos + ' text-right uppercase print:hidden'} value={v} onChange={(e) => set(e.target.value)} aria-label={`Título de la columna ${i + 1}`} />
                    <span className="hidden print:inline">{v}</span>
                  </th>
                ))}
                <th className={th + ' text-right'}>Var {colB.replace(/^PRESUPUESTO/i, 'PPTO')} vs {colA.replace(/^PROY\.\s*CIERRE/i, 'PROY')}</th>
                <th className={th + ' w-[9%] text-right'}>%</th>
              </tr>
            </thead>
            <tbody>
              {filas.map((f, i) => {
                if (f.tipo === 'separador') return <tr key={i} aria-hidden="true"><td colSpan={5} className="h-5 border-b border-line" /></tr>
                const e = ESTILO[f.tipo] ?? ESTILO.total
                return (
                  <tr key={i} className={e.fila}>
                    <td className={celda + ' ' + (e.nombre ?? '')}>{f.nombre}</td>
                    <td className={celda + num}>{fmt(f.a)}</td>
                    <td className={celda + num}>{fmt(f.b)}</td>
                    <td className={celda + num}>{fmt(f.var)}</td>
                    <td className={celda + num}>{pct(f.pct)}</td>
                  </tr>
                )
              })}
            </tbody>
          </table>
        </div>
      </div>
    </section>
  )
}

Informe.layout = (page) => <AppLayout>{page}</AppLayout>
