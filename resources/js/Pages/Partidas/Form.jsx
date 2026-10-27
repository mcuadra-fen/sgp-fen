import { useState } from 'react'
import { Link, useForm } from '@inertiajs/react'
import AppLayout from '../../Layouts/AppLayout.jsx'
import { MESES, dist, fmt, parse, sum } from '../../lib/data.js'

const CAMPOS = [
  ['dpto', 'DPTO', true],
  ['unidad', 'UNIDAD', true],
  ['actividad', 'ACTIVIDAD', true],
  ['subactividad', 'SUBACTIVIDAD', true],
  ['sigma', 'CLASIFICACIÓN SIGMA', true],
  ['cuenta', 'CUENTA', true],
  ['dep_codigo', 'dep_codigo', false],
  ['un_codigo', 'un_codigo', false],
]

export default function Form({ partida, meses, editando, listas, codigos }) {
  const form = useForm({
    dpto: partida.dpto ?? '',
    unidad: partida.unidad ?? '',
    actividad: partida.actividad ?? '',
    subactividad: partida.subactividad ?? '',
    sigma: partida.sigma ?? '',
    cuenta: partida.cuenta ?? '',
    dep_codigo: partida.dep_codigo ?? '',
    un_codigo: partida.un_codigo ?? '',
    meses: meses.map(fmt),
  })
  const [total, setTotal] = useState(meses.some((v) => v) ? fmt(sum(meses)) : '')
  const [modo, setModo] = useState('uniforme')
  const [mes, setMes] = useState(0)

  const cambiar = (campo) => (e) => {
    const v = e.target.value
    form.setData((d) => {
      const next = { ...d, [campo]: v }
      // Al elegir una unidad ya conocida en una partida nueva, se completan sus códigos
      if (campo === 'unidad' && !editando && codigos[v]) {
        next.dep_codigo = codigos[v][0]
        next.un_codigo = codigos[v][1]
      }
      return next
    })
  }
  const setMesValor = (i, v) => form.setData('meses', form.data.meses.map((x, j) => (j === i ? v : x)))
  const aplicar = () => form.setData('meses', dist(parse(total), modo, Number(mes)).map(fmt))
  const suma = sum(form.data.meses.map(parse))
  const errores = Object.values(form.errors)

  const enviar = (e) => {
    e.preventDefault()
    if (editando) form.put(`/partidas/${partida.id}`)
    else form.post('/partidas')
  }

  return (
    <div className="mx-auto max-w-[760px]">
      <form onSubmit={enviar} noValidate autoComplete="off" className="panel flex flex-col gap-4">
        <div className="flex items-center justify-between gap-3">
          <h1 className="text-[17px] font-semibold">{editando ? 'Editar partida' : 'Nueva partida'}</h1>
          <Link href="/partidas" className="btn btn-ghost">Cancelar</Link>
        </div>

        {errores.length > 0 && (
          <div className="rounded-md bg-badSoft px-3 py-2 text-[13px] text-bad" role="alert">
            {errores.map((m) => <div key={m}>{m}</div>)}
          </div>
        )}

        <div className="grid gap-3 sm:grid-cols-2">
          {CAMPOS.map(([k, etiqueta, lista]) => (
            <div key={k} className="flex min-w-0 flex-col gap-1">
              <label className="lbl" htmlFor={'f_' + k}>{etiqueta}</label>
              <input
                id={'f_' + k}
                className={'inp ' + (lista ? '' : 'font-mono')}
                list={lista ? 'dl_' + k : undefined}
                inputMode={lista ? undefined : 'numeric'}
                value={form.data[k]}
                onChange={cambiar(k)}
                autoFocus={k === 'unidad' && !editando}
              />
            </div>
          ))}
        </div>
        {Object.entries(listas).map(([k, valores]) => (
          <datalist key={k} id={'dl_' + k}>{valores.map((v) => <option key={v} value={v} />)}</datalist>
        ))}

        <div className="flex flex-wrap items-end gap-2 rounded-lg border border-line bg-surface2 p-3">
          <div className="flex min-w-[130px] flex-[1_1_130px] flex-col gap-1">
            <label className="lbl" htmlFor="d_total">Monto anual (CLP)</label>
            <input id="d_total" className="inp tnum font-mono" inputMode="numeric" placeholder="0" value={total}
              onChange={(e) => setTotal(e.target.value)} onBlur={() => setTotal((t) => (t ? fmt(parse(t)) : ''))} />
          </div>
          <div className="flex min-w-[130px] flex-[1_1_130px] flex-col gap-1">
            <label className="lbl" htmlFor="d_modo">Distribuir</label>
            <select id="d_modo" className="inp" value={modo} onChange={(e) => setModo(e.target.value)}>
              <option value="uniforme">Parejo, 12 meses</option>
              <option value="trimestral">Trimestral (ene, abr, jul, oct)</option>
              <option value="semestral">Semestral (ene, jul)</option>
              <option value="unico">En un solo mes</option>
            </select>
          </div>
          {modo === 'unico' && (
            <div className="flex min-w-[130px] flex-[1_1_130px] flex-col gap-1">
              <label className="lbl" htmlFor="d_mes">Mes</label>
              <select id="d_mes" className="inp" value={mes} onChange={(e) => setMes(e.target.value)}>
                {MESES.map((m, i) => <option key={m} value={i}>{m}</option>)}
              </select>
            </div>
          )}
          <button type="button" className="btn" onClick={aplicar}>Aplicar</button>
        </div>

        <div>
          <div className="mb-1.5 text-xs text-ink3">Monto por mes</div>
          <div className="grid grid-cols-3 gap-2 sm:grid-cols-4">
            {MESES.map((m, i) => (
              <div key={m} className="flex min-w-0 flex-col gap-1">
                <label className="lbl" htmlFor={'m' + i}>{m}</label>
                <input id={'m' + i} className="inp tnum text-right font-mono" inputMode="numeric"
                  value={form.data.meses[i]}
                  onFocus={(e) => e.target.select()}
                  onChange={(e) => setMesValor(i, e.target.value)}
                  onBlur={() => setMesValor(i, fmt(parse(form.data.meses[i])))} />
              </div>
            ))}
          </div>
          <div className="flex items-baseline justify-between pt-2 text-[13px]">
            <span>Monto total</span>
            <b className="tnum font-mono text-lg">{fmt(suma)}</b>
          </div>
        </div>

        <div className="flex justify-end gap-2 border-t border-line pt-3">
          <Link href="/partidas" className="btn">Cancelar</Link>
          <button type="submit" className="btn btn-primary" disabled={form.processing}>Guardar partida</button>
        </div>
      </form>
    </div>
  )
}

Form.layout = (page) => <AppLayout>{page}</AppLayout>
