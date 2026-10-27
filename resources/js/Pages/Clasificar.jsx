import { useEffect, useMemo, useState } from 'react'
import { router } from '@inertiajs/react'
import AppLayout from '../Layouts/AppLayout.jsx'
import { fmt } from '../lib/data.js'

const opts = { preserveScroll: true, preserveState: true }
const SIN = 'sin' // valor del filtro «Sin categoría»

/** Botón que pide un segundo clic para confirmar (mismo patrón que «Eliminar» en Presupuesto). */
function Confirmar({ children, pregunta = '¿Seguro?', onConfirm, className = '' }) {
  const [armado, setArmado] = useState(false)
  return (
    <button
      className={'btn btn-sm ' + (armado ? 'btn-danger ' : '') + className}
      onClick={() => {
        if (!armado) {
          setArmado(true)
          setTimeout(() => setArmado(false), 3000)
          return
        }
        setArmado(false)
        onConfirm()
      }}
    >
      {armado ? pregunta : children}
    </button>
  )
}

function Categorias({ categorias, errors }) {
  const [nueva, setNueva] = useState('')
  const [editando, setEditando] = useState(null) // [id, nombre]

  const crear = (e) => {
    e.preventDefault()
    router.post('/categorias', { nombre: nueva }, { ...opts, onSuccess: () => setNueva('') })
  }
  const renombrar = (e) => {
    e.preventDefault()
    router.patch(`/categorias/${editando[0]}`, { nombre: editando[1] }, { ...opts, onSuccess: () => setEditando(null) })
  }

  return (
    <div className="panel flex flex-col gap-3">
      <h2 className="panel-title !mb-0">Categorías</h2>
      <div className="flex flex-wrap items-center gap-2">
        {categorias.length === 0 && <span className="text-xs text-ink3">Todavía no hay categorías.</span>}
        {categorias.map((c) =>
          editando?.[0] === c.id ? (
            <form key={c.id} onSubmit={renombrar} className="flex items-center gap-1">
              <input autoFocus className="inp !w-48 py-1 text-xs" value={editando[1]} onChange={(e) => setEditando([c.id, e.target.value])} aria-label="Nuevo nombre" />
              <button className="btn btn-sm btn-primary">Guardar</button>
              <button type="button" className="btn btn-sm btn-ghost" onClick={() => setEditando(null)}>Cancelar</button>
            </form>
          ) : (
            <span key={c.id} className="flex items-center gap-1 rounded-full border border-line py-0.5 pl-3 pr-1">
              <span className="text-[13px] font-medium">{c.nombre}</span>
              <span className="text-xs text-ink3">{c.n}</span>
              <button className="btn btn-sm btn-ghost !px-1.5" onClick={() => setEditando([c.id, c.nombre])} aria-label={`Renombrar ${c.nombre}`}>Renombrar</button>
              <Confirmar className="btn-ghost !px-1.5" pregunta={c.n ? `¿Quitar de ${c.n}?` : '¿Eliminar?'} onConfirm={() => router.delete(`/categorias/${c.id}`, opts)}>
                Eliminar
              </Confirmar>
            </span>
          ),
        )}
        <form onSubmit={crear} className="flex items-center gap-1">
          <input className="inp !w-52 py-1 text-xs" placeholder="Nueva categoría…" value={nueva} onChange={(e) => setNueva(e.target.value)} aria-label="Nombre de la nueva categoría" />
          <button className="btn btn-sm" disabled={!nueva.trim()}>Agregar</button>
        </form>
      </div>
      {errors.nombre && <p className="m-0 text-xs text-bad">{errors.nombre}</p>}
      <p className="m-0 text-xs text-ink3">
        Las categorías son comunes a todos los años; el número indica cuántas partidas de este año tiene cada una.
        Al eliminar una categoría sus partidas quedan sin categoría (no se borra ninguna partida).
      </p>
    </div>
  )
}

function Observaciones({ partida, errors }) {
  const [texto, setTexto] = useState('')
  const agregar = () => {
    if (!texto.trim()) return
    router.post(`/partidas/${partida.id}/observaciones`, { texto }, { ...opts, onSuccess: () => setTexto('') })
  }
  return (
    <div className="flex flex-col gap-2 py-1">
      {partida.observaciones.length === 0 && <p className="m-0 text-xs text-ink3">Sin observaciones todavía.</p>}
      {partida.observaciones.map((o) => (
        <div key={o.id} className="flex items-start gap-3 rounded-md border border-line bg-surface px-3 py-2">
          <div className="min-w-0 flex-1">
            <p className="m-0 whitespace-pre-wrap break-words text-[13px]">{o.texto}</p>
            <span className="text-[11px] text-ink3">{o.fecha}</span>
          </div>
          <Confirmar className="btn-ghost" onConfirm={() => router.delete(`/observaciones/${o.id}`, opts)}>Eliminar</Confirmar>
        </div>
      ))}
      <div className="flex flex-wrap items-end gap-2">
        <textarea
          className="inp min-h-[60px] flex-1 resize-y text-[13px]"
          placeholder="Escriba una observación… (Ctrl+Enter para agregar)"
          value={texto}
          onChange={(e) => setTexto(e.target.value)}
          onKeyDown={(e) => { if (e.key === 'Enter' && (e.ctrlKey || e.metaKey)) agregar() }}
          aria-label={`Nueva observación para ${partida.cuenta}`}
        />
        <button className="btn btn-primary btn-sm" disabled={!texto.trim()} onClick={agregar}>Agregar observación</button>
      </div>
      {errors.texto && <p className="m-0 text-xs text-bad">{errors.texto}</p>}
    </div>
  )
}

export default function Clasificar({ partidas, categorias, errors }) {
  const [q, setQ] = useState('')
  const [actividad, setActividad] = useState('')
  const [subactividad, setSubactividad] = useState('')
  const [categoria, setCategoria] = useState('')
  const [conObs, setConObs] = useState(false)
  const [abierta, setAbierta] = useState(null)
  const [seleccion, setSeleccion] = useState(() => new Set())
  const [destino, setDestino] = useState('')

  useEffect(() => {
    setSeleccion((s) => new Set(partidas.filter((p) => s.has(p.id)).map((p) => p.id)))
  }, [partidas])

  const uniq = (k, rs = partidas) => [...new Set(rs.map((r) => r[k]))].filter(Boolean).sort((a, b) => a.localeCompare(b, 'es'))
  const actividades = useMemo(() => uniq('actividad'), [partidas])
  // Solo las subactividades de la actividad elegida
  const subactividades = useMemo(() => uniq('subactividad', actividad ? partidas.filter((r) => r.actividad === actividad) : partidas), [partidas, actividad])

  const lista = useMemo(() => {
    const t = q.toLowerCase()
    return partidas.filter((r) =>
      (!actividad || r.actividad === actividad) && (!subactividad || r.subactividad === subactividad) &&
      (!categoria || (categoria === SIN ? !r.categoria_id : r.categoria_id === Number(categoria))) &&
      (!conObs || r.observaciones.length > 0) &&
      (!t || [r.cuenta, r.unidad, r.actividad, r.subactividad, r.sigma, ...r.observaciones.map((o) => o.texto)]
        .join(' ').toLowerCase().includes(t)))
  }, [partidas, q, actividad, subactividad, categoria, conObs])

  const sinCategoria = partidas.filter((p) => !p.categoria_id).length
  // Sumas de lo que se ve (según filtros) y de lo marcado
  const totalVisible = lista.reduce((s, r) => s + r.total, 0)
  const totalMarcado = partidas.reduce((s, r) => s + (seleccion.has(r.id) ? r.total : 0), 0)
  const marcadas = lista.filter((r) => seleccion.has(r.id)).length
  const todas = lista.length > 0 && marcadas === lista.length

  const alternar = (id) => {
    const s = new Set(seleccion)
    if (s.has(id)) s.delete(id)
    else s.add(id)
    setSeleccion(s)
  }
  const alternarTodas = () => {
    const s = new Set(seleccion)
    lista.forEach((r) => (todas ? s.delete(r.id) : s.add(r.id)))
    setSeleccion(s)
  }
  const asignar = (ids, categoriaId, alTerminar) =>
    router.post('/clasificar/asignar', { ids, categoria_id: categoriaId || null }, { ...opts, onSuccess: alTerminar })

  const filtro = 'flex min-w-0 max-w-[280px] flex-[1_1_150px] flex-col gap-1'
  const th = 'sticky top-0 z-[2] h-10 whitespace-nowrap border-b border-lineStrong bg-surface2 px-2.5 text-left text-[11px] font-semibold uppercase tracking-wider text-ink2'
  const td = 'border-b border-line px-2.5 py-2 align-top'

  return (
    <section className="flex flex-col gap-3">
      <Categorias categorias={categorias} errors={errors} />

      <div className="flex flex-wrap items-end gap-2.5">
        <div className={filtro}>
          <label className="lbl" htmlFor="cq">Buscar</label>
          <input id="cq" type="search" className="inp" placeholder="Cuenta, actividad, observación…" value={q} onChange={(e) => setQ(e.target.value)} />
        </div>
        <div className={filtro}>
          <label className="lbl" htmlFor="ca">Actividad</label>
          <select id="ca" className="inp" value={actividad} onChange={(e) => {
            setActividad(e.target.value)
            if (e.target.value && subactividad && !partidas.some((r) => r.actividad === e.target.value && r.subactividad === subactividad)) setSubactividad('')
          }}>
            <option value="">Todas las actividades</option>
            {actividades.map((a) => <option key={a}>{a}</option>)}
          </select>
        </div>
        <div className={filtro}>
          <label className="lbl" htmlFor="cs">Subactividad</label>
          <select id="cs" className="inp" value={subactividad} onChange={(e) => setSubactividad(e.target.value)}>
            <option value="">Todas las subactividades</option>
            {subactividades.map((s) => <option key={s}>{s}</option>)}
          </select>
        </div>
        <div className={filtro}>
          <label className="lbl" htmlFor="cc">Categoría</label>
          <select id="cc" className="inp" value={categoria} onChange={(e) => setCategoria(e.target.value)}>
            <option value="">Todas las categorías</option>
            <option value={SIN}>Sin categoría ({sinCategoria})</option>
            {categorias.map((c) => <option key={c.id} value={c.id}>{c.nombre} ({c.n})</option>)}
          </select>
        </div>
        <label className="flex items-center gap-2 pb-2 text-[13px]">
          <input type="checkbox" className="h-4 w-4 accent-[var(--accent)]" checked={conObs} onChange={(e) => setConObs(e.target.checked)} />
          Solo con observaciones
        </label>
      </div>

      {seleccion.size > 0 && (
        <div className="flex flex-wrap items-center gap-2 rounded-md border border-accent bg-accentSoft px-3 py-2" role="region" aria-label="Partidas seleccionadas">
          <b className="text-[13px]">{seleccion.size} {seleccion.size === 1 ? 'partida seleccionada' : 'partidas seleccionadas'}</b>
          <span className="tnum font-mono text-[13px]">suman {fmt(totalMarcado)}</span>
          <div className="flex-1" />
          <select className="inp !w-auto py-1 text-xs" value={destino} onChange={(e) => setDestino(e.target.value)} aria-label="Categoría a asignar">
            <option value="">Elegir categoría…</option>
            {categorias.map((c) => <option key={c.id} value={c.id}>{c.nombre}</option>)}
          </select>
          <button className="btn btn-sm btn-primary" disabled={!destino} onClick={() => asignar([...seleccion], destino, () => { setSeleccion(new Set()); setDestino('') })}>
            Asignar
          </button>
          <button className="btn btn-sm" onClick={() => asignar([...seleccion], null, () => setSeleccion(new Set()))}>Quitar categoría</button>
          <button className="btn btn-sm btn-ghost" onClick={() => setSeleccion(new Set())}>Quitar selección</button>
        </div>
      )}

      <div className="max-h-[calc(100vh-260px)] min-h-[200px] overflow-auto rounded-lg border border-line bg-surface">
        <table className="w-full min-w-[1000px] border-separate border-spacing-0 text-[13px]">
          <thead>
            <tr>
              <th className={th + ' w-10'}>
                <input type="checkbox" className="h-4 w-4 cursor-pointer accent-[var(--accent)]" aria-label="Seleccionar todas las partidas visibles"
                  checked={todas} ref={(el) => { if (el) el.indeterminate = marcadas > 0 && !todas }} onChange={alternarTodas} disabled={!lista.length} />
              </th>
              <th className={th}>Cuenta · unidad · actividad</th>
              <th className={th}>Subactividad</th>
              <th className={th}>Clasificación SIGMA</th>
              <th className={th + ' text-right'}>Monto total</th>
              <th className={th + ' w-[220px]'}>Categoría</th>
              <th className={th + ' w-[150px]'}>Observaciones</th>
            </tr>
          </thead>
          <tbody>
            {lista.length === 0 && (
              <tr><td colSpan={7} className="p-8 text-center text-ink2">No hay partidas con estos filtros.</td></tr>
            )}
            {lista.map((r) => {
              const marcada = seleccion.has(r.id)
              const abiertaEsta = abierta === r.id
              const n = r.observaciones.length
              return [
                <tr key={r.id} className={marcada ? '[&>td]:bg-accentSoft' : ''}>
                  <td className={td}>
                    <input type="checkbox" className="mt-1 h-4 w-4 cursor-pointer accent-[var(--accent)]" aria-label={`Seleccionar ${r.cuenta}`} checked={marcada} onChange={() => alternar(r.id)} />
                  </td>
                  <td className={td}>
                    <div className="font-medium leading-tight">{r.cuenta}</div>
                    <div className="text-xs leading-tight text-ink3">
                      {r.unidad} · {r.actividad}
                    </div>
                  </td>
                  <td className={td + ' text-xs leading-tight text-ink2'}>{r.subactividad}</td>
                  <td className={td}><span className="pill whitespace-normal">{r.sigma}</span></td>
                  <td className={td + ' tnum whitespace-nowrap text-right font-mono'}>{fmt(r.total)}</td>
                  <td className={td}>
                    <select
                      className={'inp py-1 text-xs ' + (r.categoria_id ? 'font-medium' : 'text-ink3')}
                      value={r.categoria_id ?? ''}
                      onChange={(e) => asignar([r.id], e.target.value)}
                      aria-label={`Categoría de ${r.cuenta}`}
                    >
                      <option value="">Sin categoría</option>
                      {categorias.map((c) => <option key={c.id} value={c.id}>{c.nombre}</option>)}
                    </select>
                  </td>
                  <td className={td}>
                    <button className={'btn btn-sm ' + (abiertaEsta ? 'border-accent text-accent' : '')} aria-expanded={abiertaEsta} onClick={() => setAbierta(abiertaEsta ? null : r.id)}>
                      {n ? `${n} ${n === 1 ? 'observación' : 'observaciones'}` : 'Agregar'} {abiertaEsta ? '▴' : '▾'}
                    </button>
                    {n > 0 && !abiertaEsta && (
                      <p className="m-0 mt-1 line-clamp-2 max-w-[150px] text-[11px] leading-tight text-ink3" title={r.observaciones[n - 1].texto}>
                        {r.observaciones[n - 1].texto}
                      </p>
                    )}
                  </td>
                </tr>,
                abiertaEsta && (
                  <tr key={`${r.id}-obs`}>
                    <td className={td + ' bg-surface2'} />
                    <td colSpan={6} className={td + ' bg-surface2'}>
                      <Observaciones partida={r} errors={errors} />
                    </td>
                  </tr>
                ),
              ]
            })}
          </tbody>
          {lista.length > 0 && (
            <tfoot>
              <tr className="font-semibold">
                <td className="sticky bottom-0 border-t border-lineStrong bg-surface2 px-2.5 py-2.5" />
                <td colSpan={3} className="sticky bottom-0 border-t border-lineStrong bg-surface2 px-2.5 py-2.5">
                  Total ({lista.length} {lista.length === 1 ? 'partida' : 'partidas'}{lista.length < partidas.length ? ' filtradas' : ''})
                </td>
                <td className="tnum sticky bottom-0 whitespace-nowrap border-t border-lineStrong bg-surface2 px-2.5 py-2.5 text-right font-mono">{fmt(totalVisible)}</td>
                <td colSpan={2} className="sticky bottom-0 border-t border-lineStrong bg-surface2 px-2.5 py-2.5" />
              </tr>
            </tfoot>
          )}
        </table>
      </div>
      <p className="m-0 text-xs text-ink3">
        {lista.length} de {partidas.length} partidas de este año. Para clasificar muchas a la vez: filtre (por ejemplo, busque «PROYECTO»),
        marque la casilla del encabezado y use «Asignar». La búsqueda también encuentra texto dentro de las observaciones.
      </p>
    </section>
  )
}

Clasificar.layout = (page) => <AppLayout>{page}</AppLayout>
