import { useRef, useState } from 'react'
import { router, useForm, usePage } from '@inertiajs/react'
import AppLayout from '../Layouts/AppLayout.jsx'
import ImportarExcel from '../components/ImportarExcel.jsx'

const ANIO_MIN = 2000
const ANIO_MAX = 2100

export default function Datos({ tsv, preview, cargados, errors }) {
  const { anio: anioVisto, version: versionVista, versiones } = usePage().props
  // El año empieza vacío a propósito: hay que indicarlo en cada carga.
  // «completar»: en la proyección, enero a junio se toman del presupuesto inicial
  const form = useForm({ texto: '', anio: '', version: 'inicial', completar: true })
  const [aviso, setAviso] = useState('')
  const [armado, setArmado] = useState(false)
  const [armadoAnio, setArmadoAnio] = useState(null)
  const area = useRef(null)
  const opts = { preserveScroll: true }

  const anio = Number(form.data.anio)
  const anioOk = Number.isInteger(anio) && anio >= ANIO_MIN && anio <= ANIO_MAX

  const proyeccion = form.data.version === 'proyeccion'
  const nombreVersion = versiones[form.data.version].toLowerCase()

  const revisar = () => form.post('/datos/revisar', opts)
  const desdeArchivo = (texto) => {
    form.setData('texto', texto)
    router.post('/datos/revisar', { ...form.data, texto }, { ...opts, preserveState: true })
  }
  const importar = (modo) => router.post('/datos/importar', { ...form.data, modo }, { ...opts, preserveState: true })

  const copiar = async () => {
    try {
      await navigator.clipboard.writeText(tsv)
      setAviso('Tabla copiada. Péguela en Excel.')
    } catch {
      area.current?.focus()
      area.current?.select()
      setAviso('Seleccioné el texto; cópielo con Ctrl+C.')
    }
    setTimeout(() => setAviso(''), 4000)
  }

  const reiniciar = () => {
    if (!armado) {
      setArmado(true)
      setTimeout(() => setArmado(false), 3500)
      return
    }
    setArmado(false)
    router.post('/datos/reiniciar', {}, opts)
  }

  const eliminarAnio = (a, v) => {
    const clave = `${a}|${v}`
    if (armadoAnio !== clave) {
      setArmadoAnio(clave)
      setTimeout(() => setArmadoAnio((x) => (x === clave ? null : x)), 3500)
      return
    }
    setArmadoAnio(null)
    router.delete(`/datos/anios/${a}?version=${v}`, opts)
  }

  const listo = preview && preview.validas > 0 && anioOk
  // Lo que dice la revisión vale solo si se hizo con el mismo año y versión que están escritos
  const vigente = preview && preview.anio === anio && preview.version === form.data.version
  const existentes = vigente ? preview.existentes : 0
  return (
    <section className="grid gap-3 md:grid-cols-[minmax(0,1.5fr)_minmax(0,1fr)]">
      <div className="panel flex flex-col gap-3">
        <h2 className="panel-title !mb-0">Pegar desde Excel</h2>
        <p className="m-0 text-xs text-ink3">
          Copie las filas de su planilla (con o sin encabezado) y péguelas aquí. Orden de columnas: DPTO, UNIDAD,
          ACTIVIDAD, SUBACTIVIDAD, CLASIFICACIÓN SIGMA, CUENTA, enero a diciembre, MONTO TOTAL, dep_codigo, un_codigo.
        </p>

        <div className="flex flex-wrap items-center gap-2">
          <label className="lbl !mb-0" htmlFor="anio">Año de la planilla</label>
          <input
            id="anio"
            type="number"
            inputMode="numeric"
            min={ANIO_MIN}
            max={ANIO_MAX}
            className={'inp !w-28 font-mono ' + (errors.anio ? '!border-bad' : '')}
            placeholder={`ej.: ${anioVisto}`}
            value={form.data.anio}
            onChange={(e) => form.setData('anio', e.target.value)}
          />
          <select
            className="inp !w-auto"
            aria-label="Versión de la planilla"
            value={form.data.version}
            onChange={(e) => form.setData('version', e.target.value)}
          >
            {Object.entries(versiones).map(([v, nombre]) => <option key={v} value={v}>{nombre}</option>)}
          </select>
          {!anioOk && <span className="text-xs text-ink3">Obligatorio: indique a qué año corresponden las filas.</span>}
        </div>
        {errors.anio && <p className="m-0 text-xs text-bad">{errors.anio}</p>}
        {errors.version && <p className="m-0 text-xs text-bad">{errors.version}</p>}
        {proyeccion && (
          <label className="flex items-start gap-2 rounded-md bg-surface2 px-3 py-2 text-xs text-ink2">
            <input
              type="checkbox"
              className="mt-0.5 h-4 w-4 accent-[var(--accent)]"
              checked={form.data.completar}
              onChange={(e) => form.setData('completar', e.target.checked)}
            />
            <span>
              Completar <b>enero a junio</b> con el presupuesto inicial{anioOk ? ` de ${anio}` : ''}. Úselo cuando la planilla
              de proyección trae solo julio a diciembre: cada fila toma enero–junio (y la categoría) de la partida igual del
              inicial (misma unidad, actividad, subactividad, SIGMA, cuenta y códigos).
            </span>
          </label>
        )}

        <ImportarExcel onTexto={desdeArchivo} />
        <textarea
          className="inp min-h-[150px] resize-y whitespace-pre font-mono text-xs"
          placeholder="Pegue aquí las filas…"
          value={form.data.texto}
          onChange={(e) => form.setData('texto', e.target.value)}
        />
        {(form.errors.texto || errors.texto) && <p className="text-xs text-bad">{form.errors.texto || errors.texto}</p>}

        {preview && (
          <div className="flex flex-wrap items-center gap-2">
            {preview.validas > 0 && <span className="pill pill-ok">{preview.validas} filas válidas</span>}
            {preview.avisos > 0 && <span className="pill pill-warn">{preview.avisos} con total pegado distinto a la suma mensual</span>}
            {existentes > 0 && <span className="pill pill-warn">{anio} ({nombreVersion}) ya tiene {existentes} partidas</span>}
            {vigente && proyeccion && form.data.completar && preview.coinciden !== null && (
              preview.iniciales === 0
                ? <span className="pill pill-warn">{anio} no tiene presupuesto inicial: enero a junio quedarán como vengan en la planilla</span>
                : <span className={'pill ' + (preview.coinciden < preview.validas ? 'pill-warn' : 'pill-ok')}>
                    {preview.coinciden} de {preview.validas} filas encontradas en el inicial
                  </span>
            )}
            {preview.errores.map((e) => <span key={e} className="pill pill-bad">{e}</span>)}
            {!preview.validas && !preview.errores.length && <span className="text-xs text-ink3">No hay nada que revisar todavía.</span>}
          </div>
        )}

        <div className="flex flex-wrap items-center gap-2">
          <button className="btn" onClick={revisar} disabled={form.processing}>Revisar</button>
          <button className="btn btn-primary" disabled={!listo} onClick={() => importar('agregar')}>
            {anioOk ? `Agregar a ${anio} (${nombreVersion})` : 'Agregar filas'}
          </button>
          <button className="btn" disabled={!listo} onClick={() => importar('reemplazar')}>
            {anioOk ? `Reemplazar ${anio} (${nombreVersion})` : 'Reemplazar año'}
          </button>
        </div>
        <p className="m-0 text-xs text-ink3">
          «Reemplazar» envía a la papelera lógica solo las partidas de ese año y versión y carga las filas pegadas; lo demás no cambia.
        </p>
      </div>

      <div className="flex flex-col gap-3">
        <div className="panel flex flex-col gap-3">
          <h2 className="panel-title !mb-0">Años cargados</h2>
          {cargados.length === 0 && <p className="m-0 text-xs text-ink3">Todavía no hay partidas en ningún año.</p>}
          {cargados.length > 0 && (
            <ul className="m-0 flex list-none flex-col p-0">
              {cargados.map(([a, v, n]) => (
                <li key={`${a}|${v}`} className="flex flex-wrap items-center gap-x-3 gap-y-1 border-b border-line py-2 last:border-b-0">
                  <b className="font-mono">{a}</b>
                  <span>{versiones[v] ?? v}</span>
                  <span className="text-xs text-ink3">{n} partidas</span>
                  {a === anioVisto && v === versionVista && <span className="pill">en pantalla</span>}
                  <button
                    className={'btn btn-sm ml-auto ' + (armadoAnio === `${a}|${v}` ? 'btn-danger' : '')}
                    onClick={() => eliminarAnio(a, v)}
                  >
                    {armadoAnio === `${a}|${v}` ? `¿Eliminar las ${n} partidas?` : 'Eliminar'}
                  </button>
                </li>
              ))}
            </ul>
          )}
          <p className="m-0 text-xs text-ink3">Al eliminar aparece «Deshacer» por unos segundos.</p>
        </div>

        <div className="panel flex flex-col gap-3">
          <h2 className="panel-title !mb-0">Exportar y reiniciar</h2>
          <p className="m-0 text-xs text-ink3">
            Descargue el presupuesto de {anioVisto} ({versiones[versionVista].toLowerCase()}) o cópielo como tabla; se pega directo en Excel o Google Sheets con las 21 columnas.
          </p>
          <textarea ref={area} readOnly tabIndex={-1} className="sr-only" value={tsv} />
          <div className="flex flex-wrap items-center gap-2">
            <button className="btn btn-primary" onClick={copiar}>Copiar tabla</button>
            <a href="/datos/exportar" className="btn">Descargar .tsv</a>
          </div>
          {aviso && <p className="m-0 text-xs text-ok">{aviso}</p>}

          <div className="flex flex-wrap items-center gap-2 border-t border-line pt-3">
            <button className="btn" onClick={() => router.post('/datos/quitar-ejemplos', {}, opts)}>Quitar filas de ejemplo</button>
            <button className="btn btn-danger" onClick={reiniciar}>
              {armado ? 'Confirmar: borrar todos los años' : 'Volver a datos iniciales'}
            </button>
          </div>
          <p className="m-0 text-xs text-ink3">
            «Volver a datos iniciales» borra definitivamente todos los años y deja solo las filas de ejemplo.
            Los datos quedan en la base de datos de la aplicación y los ve cualquier persona con acceso a ella.
          </p>
        </div>
      </div>
    </section>
  )
}

Datos.layout = (page) => <AppLayout>{page}</AppLayout>
