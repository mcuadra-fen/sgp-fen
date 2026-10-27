import { useEffect, useMemo, useState } from 'react'
import { Link, router } from '@inertiajs/react'
import AppLayout from '../../Layouts/AppLayout.jsx'
import Grilla from '../../components/Grilla.jsx'
import { fmt, sum, xsrf } from '../../lib/data.js'

export default function Index({ partidas }) {
  // Copia local para que la edición de celdas se vea al instante; el servidor la confirma.
  const [rows, setRows] = useState(partidas)
  useEffect(() => { setRows(partidas) }, [partidas])

  // Partidas marcadas para eliminar juntas; se descartan las que ya no existen
  const [seleccion, setSeleccion] = useState(() => new Set())
  const [armadoLote, setArmadoLote] = useState(false)
  useEffect(() => {
    setSeleccion((s) => new Set(partidas.filter((p) => s.has(p.id)).map((p) => p.id)))
  }, [partidas])

  const [q, setQ] = useState('')
  const [actividad, setActividad] = useState('')
  const [sigma, setSigma] = useState('')
  const [error, setError] = useState('')

  const uniq = (k) => [...new Set(rows.map((r) => r[k]))].filter(Boolean).sort((a, b) => a.localeCompare(b, 'es'))
  const actividades = useMemo(() => uniq('actividad'), [rows])
  const sigmas = useMemo(() => uniq('sigma'), [rows])

  const lista = useMemo(() => {
    const t = q.toLowerCase()
    return rows.filter((r) =>
      (!actividad || r.actividad === actividad) && (!sigma || r.sigma === sigma) &&
      (!t || [r.dpto, r.unidad, r.actividad, r.subactividad, r.sigma, r.cuenta, r.dep_codigo, r.un_codigo]
        .join(' ').toLowerCase().includes(t)))
  }, [rows, q, actividad, sigma])

  const poner = (id, i, n) =>
    setRows((rs) => rs.map((r) => (r.id === id ? { ...r, meses: r.meses.map((v, j) => (j === i ? n : v)) } : r)))

  const guardarCelda = async (id, i, n) => {
    const anterior = rows.find((r) => r.id === id).meses[i]
    poner(id, i, n)
    try {
      const res = await fetch(`/partidas/${id}/mes`, {
        method: 'PATCH',
        headers: {
          'Content-Type': 'application/json',
          Accept: 'application/json',
          'X-Requested-With': 'XMLHttpRequest',
          'X-XSRF-TOKEN': xsrf(),
        },
        body: JSON.stringify({ mes: i + 1, monto: n }),
      })
      if (!res.ok) throw new Error(String(res.status))
      setError('')
    } catch {
      poner(id, i, anterior)
      setError('No se pudo guardar el cambio; se restauró el monto anterior. Recargue la página si el problema sigue.')
    }
  }

  const opts = { preserveScroll: true }

  const ocultas = [...seleccion].filter((id) => !lista.some((r) => r.id === id)).length
  const eliminarLote = () => {
    if (!armadoLote) {
      setArmadoLote(true)
      setTimeout(() => setArmadoLote(false), 3500)
      return
    }
    setArmadoLote(false)
    router.post('/partidas/eliminar', { ids: [...seleccion] }, { ...opts, onSuccess: () => setSeleccion(new Set()) })
  }

  const totalMarcado = rows.reduce((s, r) => s + (seleccion.has(r.id) ? sum(r.meses) : 0), 0)

  const filtro = 'flex min-w-0 max-w-[300px] flex-[1_1_160px] flex-col gap-1'

  return (
    <section>
      <div className="mb-3 flex flex-wrap items-end gap-2.5">
        <div className={filtro}>
          <label className="lbl" htmlFor="fq">Buscar</label>
          <input id="fq" type="search" className="inp" placeholder="Cuenta, actividad, código…" value={q} onChange={(e) => setQ(e.target.value)} />
        </div>
        <div className={filtro}>
          <label className="lbl" htmlFor="fa">Actividad</label>
          <select id="fa" className="inp" value={actividad} onChange={(e) => setActividad(e.target.value)}>
            <option value="">Todas las actividades</option>
            {actividades.map((a) => <option key={a}>{a}</option>)}
          </select>
        </div>
        <div className={filtro}>
          <label className="lbl" htmlFor="fs">Clasificación SIGMA</label>
          <select id="fs" className="inp" value={sigma} onChange={(e) => setSigma(e.target.value)}>
            <option value="">Todas las clasificaciones</option>
            {sigmas.map((s) => <option key={s}>{s}</option>)}
          </select>
        </div>
        <div className="flex-1" />
        <Link href="/partidas/create" className="btn btn-primary">+ Nueva partida</Link>
      </div>

      {error && <div className="mb-3 rounded-md bg-badSoft px-3 py-2 text-[13px] text-bad" role="alert">{error}</div>}

      {seleccion.size > 0 && (
        <div className="mb-3 flex flex-wrap items-center gap-2 rounded-md border border-accent bg-accentSoft px-3 py-2" role="region" aria-label="Partidas seleccionadas">
          <b className="text-[13px]">{seleccion.size} {seleccion.size === 1 ? 'partida seleccionada' : 'partidas seleccionadas'}</b>
          <span className="tnum font-mono text-[13px]">suman {fmt(totalMarcado)}</span>
          {ocultas > 0 && <span className="text-xs text-ink2">({ocultas} no visibles con los filtros actuales)</span>}
          <div className="flex-1" />
          <button className="btn btn-sm" onClick={() => setSeleccion(new Set())}>Quitar selección</button>
          <button className={'btn btn-sm ' + (armadoLote ? 'btn-danger' : '')} onClick={eliminarLote}>
            {armadoLote ? `¿Eliminar ${seleccion.size}? Confirmar` : `Eliminar ${seleccion.size === 1 ? 'seleccionada' : `las ${seleccion.size}`}`}
          </button>
        </div>
      )}

      <Grilla
        partidas={lista}
        seleccion={seleccion}
        onSeleccion={setSeleccion}
        onCell={guardarCelda}
        onDuplicar={(r) => router.post(`/partidas/${r.id}/duplicar`, {}, opts)}
        onEliminar={(r) => router.delete(`/partidas/${r.id}`, opts)}
      />
      <p className="mt-2 text-xs text-ink3">
        Edite los montos mensuales directamente en la grilla; cada cambio se guarda al salir de la celda.
        El monto total y los totales al pie se recalculan solos.
      </p>
    </section>
  )
}

Index.layout = (page) => <AppLayout>{page}</AppLayout>
