import { useEffect, useMemo, useState } from 'react'
import { AB, MESES, parse } from '../lib/data.js'

const CELDA = /^[A-Z]{1,3}[1-9]\d*$/
const VISTA_FILAS = 40
const VISTA_COLS = 30
// Orden en que el servidor espera las columnas (Presupuesto::CABECERA)
const CAMPOS = ['DPTO', 'UNIDAD', 'ACTIVIDAD', 'SUBACTIVIDAD', 'CLASIFICACIÓN SIGMA', 'CUENTA', ...MESES, 'MONTO TOTAL', 'dep_codigo', 'un_codigo']
const OBLIGATORIOS = [1, 5] // UNIDAD y CUENTA
const TOTAL = 18
const NO_IMPORTAR = -1

const lleno = (cell) => cell && cell.v !== undefined && cell.v !== null && String(cell.v).trim() !== ''
const texto = (ws, XLSX, r, c) => ws[XLSX.utils.encode_cell({ r, c })]?.w ?? ''
const norm = (s) => String(s).normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase().replace(/[^a-z0-9]/g, '')

// Nombre de encabezado normalizado -> campo; acepta variantes habituales
const ALIAS = new Map([
  ...CAMPOS.map((c, i) => [norm(c), i]),
  ...AB.map((m, i) => [m, 6 + i]),
  ['sigma', 4], ['clasificacion', 4], ['total', TOTAL], ['montoanual', TOTAL], ['totalanual', TOTAL],
  ['departamento', 0], ['depcod', 19], ['uncod', 20],
])
const campoDe = (s) => (ALIAS.has(norm(s)) ? ALIAS.get(norm(s)) : NO_IMPORTAR)

/** Área con datos reales; '!ref' de Excel incluye celdas vacías con formato (A1:Z1000). */
function extension(ws) {
  let s = null
  let e = null
  for (const k of Object.keys(ws)) {
    if (k[0] === '!' || !lleno(ws[k])) continue
    const m = /^([A-Z]+)(\d+)$/.exec(k)
    if (!m) continue
    const c = m[1].split('').reduce((n, ch) => n * 26 + ch.charCodeAt(0) - 64, 0) - 1
    const r = Number(m[2]) - 1
    if (!s) { s = { r, c }; e = { r, c }; continue }
    s = { r: Math.min(s.r, r), c: Math.min(s.c, c) }
    e = { r: Math.max(e.r, r), c: Math.max(e.c, c) }
  }
  return s ? { s, e } : null
}

/**
 * Busca la tabla del presupuesto: la fila de encabezado (celda «DPTO»), su última columna con
 * título y la última fila con UNIDAD o CUENTA.
 */
export function detectar(ws, XLSX) {
  const ext = extension(ws)
  if (!ext) return null
  let s = ext.s
  let finCol = ext.e.c
  let encabezado = false
  busca: for (let r = ext.s.r; r <= Math.min(ext.e.r, ext.s.r + 50); r++) {
    for (let c = ext.s.c; c <= ext.e.c; c++) {
      if (texto(ws, XLSX, r, c).trim().toUpperCase() !== 'DPTO') continue
      s = { r, c }
      encabezado = true
      finCol = c
      for (let k = c; k <= ext.e.c; k++) if (texto(ws, XLSX, r, k).trim() !== '') finCol = k
      break busca
    }
  }
  let finFila = ext.e.r
  if (encabezado) {
    // UNIDAD y CUENTA se buscan por su título, por si hay columnas extra antes
    const cols = [1, 5].map((i) => {
      for (let k = s.c; k <= finCol; k++) if (campoDe(texto(ws, XLSX, s.r, k)) === i) return k
      return s.c + i
    })
    while (finFila > s.r && !cols.some((c) => lleno(ws[XLSX.utils.encode_cell({ r: finFila, c })]))) finFila--
  }
  return { s, e: { r: finFila, c: finCol }, encabezado }
}

/** Con encabezado, cada columna va al campo de igual nombre; sin él, por posición. */
export function asignar(titulos, conEncabezado) {
  const usados = new Set()
  return titulos.map((t, j) => {
    const f = conEncabezado ? campoDe(t) : (j < CAMPOS.length ? j : NO_IMPORTAR)
    if (f === NO_IMPORTAR || usados.has(f)) return NO_IMPORTAR
    usados.add(f)
    return f
  })
}

/** Filas de datos -> texto con las 21 columnas en el orden de CAMPOS, según el mapa de columnas. */
export function aTexto(datos, mapa) {
  const colDe = CAMPOS.map((_, f) => mapa.indexOf(f))
  // parseMonto descarta todo lo que no es dígito: 1234.5 se leería como 12345, así que se redondea aquí
  const limpio = (v) => (typeof v === 'number' ? String(Math.round(v)) : String(v ?? '').replace(/[\t\r\n]+/g, ' ').trim())
  return datos.map((fila) => {
    const out = CAMPOS.map((_, f) => (colDe[f] >= 0 ? limpio(fila[colDe[f]]) : ''))
    // Sin columna de total se usa la suma de los meses (si no, el servidor avisaría diferencias)
    if (colDe[TOTAL] < 0) out[TOTAL] = String(out.slice(6, 18).reduce((s, v) => s + parse(v), 0))
    return out.join('\t')
  }).join('\n')
}

/**
 * Lee un .xlsx en el navegador y entrega el rango elegido como texto separado por tabulaciones,
 * con las columnas reordenadas al formato que espera el servidor.
 */
export default function ImportarExcel({ onTexto }) {
  const [XLSX, setXLSX] = useState(null)
  const [libro, setLibro] = useState(null)
  const [hoja, setHoja] = useState('')
  const [desde, setDesde] = useState('')
  const [hasta, setHasta] = useState('')
  const [detectado, setDetectado] = useState(null)
  const [mapa, setMapa] = useState([]) // por cada columna del rango: índice en CAMPOS o NO_IMPORTAR
  const [error, setError] = useState('')

  const ws = libro && hoja ? libro.Sheets[hoja] : null

  const elegirHoja = (nombre, wb = libro, lib = XLSX) => {
    setHoja(nombre)
    const t = detectar(wb.Sheets[nombre], lib)
    setDetectado(t)
    setDesde(t ? lib.utils.encode_cell(t.s) : '')
    setHasta(t ? lib.utils.encode_cell(t.e) : '')
  }

  const abrir = async (e) => {
    const file = e.target.files?.[0]
    e.target.value = ''
    if (!file) return
    setError('')
    try {
      const lib = XLSX || (await import('xlsx'))
      const wb = lib.read(await file.arrayBuffer())
      setXLSX(lib)
      setLibro(wb)
      elegirHoja(wb.SheetNames[0], wb, lib)
    } catch {
      setLibro(null)
      setError('No se pudo leer el archivo. ¿Es un .xlsx, .xls o .csv?')
    }
  }

  const d = desde.trim().toUpperCase()
  const h = hasta.trim().toUpperCase()
  const sel = useMemo(() => {
    if (!XLSX || !CELDA.test(d) || !CELDA.test(h)) return null
    const r = XLSX.utils.decode_range(`${d}:${h}`)
    // decode_range no ordena los extremos: Desde puede quedar después de Hasta
    if (r.s.r > r.e.r) [r.s.r, r.e.r] = [r.e.r, r.s.r]
    if (r.s.c > r.e.c) [r.s.c, r.e.c] = [r.e.c, r.s.c]
    return r
  }, [XLSX, d, h])
  const nCols = sel ? sel.e.c - sel.s.c + 1 : 0

  // Filas del rango (sin filas vacías); la primera puede ser el encabezado
  const filas = useMemo(() => {
    if (!sel || !ws) return []
    return XLSX.utils.sheet_to_json(ws, { header: 1, range: sel, raw: true, defval: '', blankrows: false })
      .filter((f) => f.some((v) => String(v).trim() !== ''))
  }, [XLSX, ws, sel])
  const titulos = sel ? Array.from({ length: nCols }, (_, j) => texto(ws, XLSX, sel.s.r, sel.s.c + j)) : []
  const conEncabezado = titulos.filter((t) => campoDe(t) !== NO_IMPORTAR).length >= 3
  const datos = conEncabezado ? filas.slice(1) : filas
  const filaDatos = sel ? sel.s.r + (conEncabezado ? 1 : 0) : 0

  // Al cambiar las columnas del rango se vuelve a asignar automáticamente
  const claveCols = sel && ws ? `${hoja}|${sel.s.r}|${sel.s.c}|${sel.e.c}` : ''
  const autoMapa = () => asignar(titulos, conEncabezado)
  useEffect(() => { setMapa(claveCols ? autoMapa() : []) }, [claveCols])
  const mapaListo = sel && mapa.length === nCols

  const cambiar = (j, f) =>
    setMapa((m) => m.map((x, k) => (k === j ? f : f !== NO_IMPORTAR && x === f ? NO_IMPORTAR : x)))

  const colDe = CAMPOS.map((_, f) => mapa.indexOf(f))
  const faltanObligatorios = OBLIGATORIOS.filter((f) => colDe[f] < 0)
  const sinColumna = CAMPOS.filter((_, f) => colDe[f] < 0 && !OBLIGATORIOS.includes(f))
  const quitadas = mapa.filter((f) => f === NO_IMPORTAR).length

  const usar = () => {
    if (!datos.length) {
      setError('El rango elegido no tiene filas con datos.')
      return
    }
    setError('')
    onTexto(aTexto(datos, mapa))
  }

  const clic = (r, c, shift) => {
    const ref = XLSX.utils.encode_cell({ r, c })
    if (shift) setHasta(ref)
    else setDesde(ref)
  }

  // Vista previa: solo el área con datos, no las celdas vacías que Excel considera usadas
  const ext = ws ? extension(ws) : null
  const r0 = ext ? ext.s.r : 0
  const c0 = ext ? ext.s.c : 0
  const nf = ext ? Math.min(ext.e.r - r0 + 1, VISTA_FILAS) : 0
  const nc = ext ? Math.min(ext.e.c - c0 + 1, VISTA_COLS) : 0
  const dentro = (r, c) => sel && r >= sel.s.r && r <= sel.e.r && c >= sel.s.c && c <= sel.e.c
  const esquina = (r, c) => sel && ((r === sel.s.r && c === sel.s.c) || (r === sel.e.r && c === sel.e.c))
  const ignorada = (c) => sel && dentro(sel.s.r, c) && mapa[c - sel.s.c] === NO_IMPORTAR
  const porDefecto = detectado && d === XLSX?.utils.encode_cell(detectado.s) && h === XLSX?.utils.encode_cell(detectado.e)

  return (
    <div className="flex flex-col gap-3 rounded-md border border-line p-3">
      <div className="flex flex-wrap items-center gap-2">
        <label className="btn btn-sm">
          {libro ? 'Cambiar archivo' : 'Abrir archivo Excel'}
          <input type="file" accept=".xlsx,.xlsm,.xls,.csv" className="sr-only" onChange={abrir} />
        </label>
        {libro && libro.SheetNames.length > 1 && (
          <label className="flex items-center gap-2 text-xs text-ink3">
            Hoja
            <select className="inp !w-auto py-[3px] text-xs text-ink" value={hoja} onChange={(e) => elegirHoja(e.target.value)}>
              {libro.SheetNames.map((n) => <option key={n} value={n}>{n}</option>)}
            </select>
          </label>
        )}
      </div>

      {!libro && (
        <p className="m-0 text-xs text-ink3">O abra el archivo: la tabla se detecta sola y no hace falta copiar ni pegar.</p>
      )}

      {libro && !ext && <p className="m-0 text-xs text-ink3">Esta hoja está vacía. Elija otra hoja.</p>}

      {ext && (
        <>
          <div className="flex flex-col gap-1">
            <p className="m-0 text-xs">
              {detectado?.encabezado
                ? porDefecto
                  ? <>Se encontró la tabla del presupuesto (encabezado en la fila {detectado.s.r + 1}). Revise las columnas abajo y presione «Usar».</>
                  : <>Rango ajustado a mano. <button className="cursor-pointer text-accent underline" onClick={() => elegirHoja(hoja)}>Volver al detectado</button></>
                : <>No se encontró el encabezado «DPTO»; se propone toda el área con datos. Ajuste las celdas si hace falta.</>}
            </p>
            <div className="flex flex-wrap items-end gap-3">
              <label className="flex flex-col gap-0.5">
                <span className="text-xs font-medium">Empieza en</span>
                <input className="inp !w-24 py-[3px] font-mono text-xs uppercase" value={desde} onChange={(e) => setDesde(e.target.value)} />
                <span className="text-[11px] text-ink3">celda de arriba a la izquierda (donde dice DPTO)</span>
              </label>
              <label className="flex flex-col gap-0.5">
                <span className="text-xs font-medium">Termina en</span>
                <input className="inp !w-24 py-[3px] font-mono text-xs uppercase" value={hasta} onChange={(e) => setHasta(e.target.value)} />
                <span className="text-[11px] text-ink3">celda de abajo a la derecha (última fila y última columna)</span>
              </label>
            </div>
          </div>

          <div className="max-h-56 overflow-auto rounded border border-line">
            <table className="border-collapse font-mono text-[11px]">
              <thead>
                <tr>
                  <th className="sticky left-0 top-0 z-[2] bg-surface2 px-1 text-ink3" />
                  {Array.from({ length: nc }, (_, j) => (
                    <th key={j} className="sticky top-0 z-[1] border-b border-line bg-surface2 px-1 font-normal text-ink3">
                      {XLSX.utils.encode_col(c0 + j)}
                    </th>
                  ))}
                </tr>
              </thead>
              <tbody>
                {Array.from({ length: nf }, (_, i) => (
                  <tr key={i}>
                    <td className="sticky left-0 border-r border-line bg-surface2 px-1 text-right text-ink3">{r0 + i + 1}</td>
                    {Array.from({ length: nc }, (_, j) => {
                      const r = r0 + i
                      const c = c0 + j
                      const v = texto(ws, XLSX, r, c)
                      return (
                        <td
                          key={j}
                          title={`${XLSX.utils.encode_cell({ r, c })}: ${v}`}
                          onClick={(e) => clic(r, c, e.shiftKey)}
                          className={
                            'max-w-[9rem] cursor-pointer select-none truncate border border-line px-1 ' +
                            (esquina(r, c) ? 'bg-accent text-accentInk'
                              : !dentro(r, c) ? 'text-ink3'
                                : ignorada(c) ? 'text-ink3 line-through' : 'bg-accentSoft')
                          }
                        >
                          {v}
                        </td>
                      )
                    })}
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
          <p className="m-0 text-[11px] text-ink3">
            Lo destacado es lo que se importará; las columnas tachadas se dejan fuera. Clic en una celda = «Empieza en», Mayús+clic = «Termina en».
            {(ext.e.r - r0 + 1 > VISTA_FILAS || ext.e.c - c0 + 1 > VISTA_COLS) && ' La vista muestra solo el comienzo de la hoja.'}
          </p>

          {mapaListo && (
            <div className="flex flex-col gap-1">
              <div className="flex flex-wrap items-baseline gap-2">
                <span className="text-xs font-medium">Columnas: a qué campo va cada una</span>
                <button className="cursor-pointer text-[11px] text-accent underline" onClick={() => setMapa(autoMapa())}>Asignar automáticamente</button>
              </div>
              <div className="overflow-x-auto rounded border border-line">
                <table className="border-collapse text-[11px]">
                  <tbody>
                    <tr>
                      {titulos.map((t, j) => (
                        <th key={j} className={'whitespace-nowrap border-b border-r border-line bg-surface2 px-1.5 py-0.5 text-left font-normal text-ink3 ' + (mapa[j] === NO_IMPORTAR ? 'line-through' : '')}>
                          <span className="font-mono">{XLSX.utils.encode_col(sel.s.c + j)}</span>{conEncabezado && t ? ` · ${t}` : ''}
                        </th>
                      ))}
                    </tr>
                    <tr>
                      {mapa.map((f, j) => (
                        <td key={j} className="border-b border-r border-line px-1 py-1">
                          <select
                            aria-label={`Campo para la columna ${XLSX.utils.encode_col(sel.s.c + j)}`}
                            className={'inp !w-auto px-1 py-0.5 text-[11px] ' + (f === NO_IMPORTAR ? 'text-ink3' : 'font-semibold')}
                            value={f}
                            onChange={(e) => cambiar(j, Number(e.target.value))}
                          >
                            <option value={NO_IMPORTAR}>✕ No importar</option>
                            {CAMPOS.map((c, k) => (
                              <option key={c} value={k}>{c}{colDe[k] >= 0 && colDe[k] !== j ? ' (en otra columna)' : ''}</option>
                            ))}
                          </select>
                        </td>
                      ))}
                    </tr>
                    <tr>
                      {mapa.map((f, j) => (
                        <td key={j} className={'max-w-[10rem] truncate whitespace-nowrap border-r border-line px-1.5 py-0.5 ' + (f === NO_IMPORTAR ? 'text-ink3 line-through' : '')}>
                          {texto(ws, XLSX, filaDatos, sel.s.c + j)}
                        </td>
                      ))}
                    </tr>
                  </tbody>
                </table>
              </div>
              <span className="text-[11px] text-ink3">
                Para dejar fuera una columna elija «✕ No importar». Si asigna un campo que ya estaba en otra columna, se mueve aquí.
                La última fila muestra el primer dato (fila {filaDatos + 1}).
              </span>
              {faltanObligatorios.length > 0 && (
                <span className="pill pill-bad self-start">Falta asignar: {faltanObligatorios.map((f) => CAMPOS[f]).join(' y ')}</span>
              )}
              {sinColumna.length > 0 && (
                <span className="text-[11px] text-ink3">
                  Sin columna (quedarán vacíos{colDe[TOTAL] < 0 ? '; MONTO TOTAL se calcula sumando los meses' : ''}): {sinColumna.join(', ')}.
                </span>
              )}
            </div>
          )}
        </>
      )}

      {ext && (
        <div className="flex flex-wrap items-center gap-2">
          {sel ? (
            <span className="pill">
              {datos.length} filas de datos · {nCols - quitadas} columnas a importar{quitadas > 0 && ` · ${quitadas} fuera`}
            </span>
          ) : (
            <span className="pill pill-bad">Escriba celdas como A1 y U48</span>
          )}
          <button className="btn btn-sm btn-primary" disabled={!mapaListo || !datos.length || faltanObligatorios.length > 0} onClick={usar}>
            Usar este rango y revisar
          </button>
        </div>
      )}

      {error && <p className="m-0 text-xs text-bad">{error}</p>}
    </div>
  )
}
