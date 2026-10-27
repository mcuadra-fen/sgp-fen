import { useRef, useState } from 'react'
import { router, useForm } from '@inertiajs/react'
import AppLayout from '../Layouts/AppLayout.jsx'

export default function Datos({ tsv, preview }) {
  const form = useForm({ texto: '' })
  const [aviso, setAviso] = useState('')
  const [armado, setArmado] = useState(false)
  const area = useRef(null)
  const opts = { preserveScroll: true }

  const revisar = () => form.post('/datos/revisar', opts)
  const importar = (modo) => router.post('/datos/importar', { texto: form.data.texto, modo }, opts)

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

  const listo = preview && preview.validas > 0
  return (
    <section className="grid gap-3 md:grid-cols-[minmax(0,1.5fr)_minmax(0,1fr)]">
      <div className="panel flex flex-col gap-3">
        <h2 className="panel-title !mb-0">Pegar desde Excel</h2>
        <p className="m-0 text-xs text-ink3">
          Copie las filas de su planilla (con o sin encabezado) y péguelas aquí. Orden de columnas: DPTO, UNIDAD,
          ACTIVIDAD, SUBACTIVIDAD, CLASIFICACIÓN SIGMA, CUENTA, enero a diciembre, MONTO TOTAL, dep_codigo, un_codigo.
        </p>
        <textarea
          className="inp min-h-[150px] resize-y whitespace-pre font-mono text-xs"
          placeholder="Pegue aquí las filas…"
          value={form.data.texto}
          onChange={(e) => form.setData('texto', e.target.value)}
        />
        {form.errors.texto && <p className="text-xs text-bad">{form.errors.texto}</p>}

        {preview && (
          <div className="flex flex-wrap items-center gap-2">
            {preview.validas > 0 && <span className="pill pill-ok">{preview.validas} filas válidas</span>}
            {preview.avisos > 0 && <span className="pill pill-warn">{preview.avisos} con total pegado distinto a la suma mensual</span>}
            {preview.errores.map((e) => <span key={e} className="pill pill-bad">{e}</span>)}
            {!preview.validas && !preview.errores.length && <span className="text-xs text-ink3">No hay nada que revisar todavía.</span>}
          </div>
        )}

        <div className="flex flex-wrap items-center gap-2">
          <button className="btn" onClick={revisar} disabled={form.processing}>Revisar</button>
          <button className="btn btn-primary" disabled={!listo} onClick={() => importar('agregar')}>Agregar filas</button>
          <button className="btn" disabled={!listo} onClick={() => importar('reemplazar')}>Reemplazar todo</button>
        </div>
        <p className="m-0 text-xs text-ink3">
          «Reemplazar todo» envía las partidas actuales a la papelera lógica y carga solo las filas pegadas.
        </p>
      </div>

      <div className="panel flex flex-col gap-3">
        <h2 className="panel-title !mb-0">Exportar y reiniciar</h2>
        <p className="m-0 text-xs text-ink3">
          Descargue el presupuesto completo o cópielo como tabla; se pega directo en Excel o Google Sheets con las 21 columnas.
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
            {armado ? 'Confirmar: descartar mis cambios' : 'Volver a datos iniciales'}
          </button>
        </div>
        <p className="m-0 text-xs text-ink3">
          Los datos quedan en la base de datos de la aplicación y los ve cualquier persona con acceso a ella.
        </p>
      </div>
    </section>
  )
}

Datos.layout = (page) => <AppLayout>{page}</AppLayout>
