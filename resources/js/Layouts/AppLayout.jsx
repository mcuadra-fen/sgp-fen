import { useEffect, useState } from 'react'
import { Link, router, usePage } from '@inertiajs/react'

const MENU = [
  ['Presupuesto', [
    ['/resumen', 'Resumen'],
    ['/partidas', 'Presupuesto'],
    ['/clasificar', 'Clasificar'],
    ['/informe', 'Informe'],
  ]],
  ['Ejecución', [
    ['/ejecucion', 'Presupuesto vs. pagado'],
    ['/pagos', 'Pagos a proveedores'],
    ['/traspasos', 'Cobros a áreas'],
  ]],
  ['Datos', [
    ['/datos', 'Importar y exportar'],
    ['/accesos', 'Accesos de áreas'],
  ]],
]
// Personas de las áreas (no administradores)
const MENU_AREA = [['Mi área', [['/mis-cobros', 'Mis cobros']]]]

export default function AppLayout({ children }) {
  const { url, props } = usePage()
  const flash = props.flash
  const [toast, setToast] = useState(null)
  const anios = props.anios.includes(props.anio) ? props.anios : [props.anio, ...props.anios]

  // Cada respuesta del servidor trae un objeto flash nuevo: se muestra el aviso si lo hay.
  useEffect(() => {
    const msg = flash?.error || flash?.ok
    if (!msg) return undefined
    setToast({ msg, undo: flash.error ? null : flash.undo })
    const t = setTimeout(() => setToast(null), flash.undo ? 8000 : 3500)
    return () => clearTimeout(t)
  }, [flash])

  const [abierto, setAbierto] = useState(false) // menú en pantallas angostas
  useEffect(() => setAbierto(false), [url])
  const activa = (href) => url === href || url.startsWith(href + '/') || url.startsWith(href + '?')
  const admin = !!props.usuario?.admin
  const menu = admin ? MENU : MENU_AREA
  const actual = menu.flatMap(([, items]) => items).find(([href]) => activa(href))?.[1]

  return (
    <div className="min-h-screen lg:pl-[248px] print:!pl-0">
      {/* Barra superior solo en pantallas angostas */}
      <header className="no-print sticky top-0 z-20 flex items-center gap-3 border-b border-line bg-bg px-4 py-2.5 lg:hidden">
        <button className="btn btn-sm" aria-label="Abrir menú" aria-expanded={abierto} onClick={() => setAbierto(true)}>☰</button>
        <b className="font-semibold">SGP-FEN</b>
        <span className="truncate text-sm text-ink2">{actual}</span>
      </header>
      {abierto && <div className="no-print fixed inset-0 z-30 bg-black/30 lg:hidden" onClick={() => setAbierto(false)} />}

      <aside
        className={
          'side no-print fixed inset-y-0 left-0 z-40 flex w-[248px] flex-col gap-5 overflow-y-auto px-4 py-5 transition-transform lg:translate-x-0 ' +
          (abierto ? 'translate-x-0 shadow-2xl' : '-translate-x-full')
        }
      >
        <div className="side-marca">
          <div className="side-logo">SGP</div>
          <div className="flex min-w-0 flex-col">
            <b className="text-[17px] font-semibold leading-tight tracking-tight">SGP-FEN</b>
            <span className="text-[11px] leading-snug" style={{ color: 'var(--s-ink-2)' }}>Sistema de Gestión de Presupuesto</span>
          </div>
        </div>

        {admin && (
        <div className="flex flex-col gap-1.5">
          <span className="side-titulo !px-0">Año y versión</span>
          <select
            className="side-sel"
            aria-label="Año"
            value={props.anio}
            onChange={(e) => router.post('/anio', { anio: e.target.value }, { preserveScroll: true })}
          >
            {anios.map((a) => <option key={a} value={a}>{a}</option>)}
          </select>
          <select
            className="side-sel"
            aria-label="Versión del presupuesto"
            value={props.version}
            onChange={(e) => router.post('/anio', { anio: props.anio, version: e.target.value }, { preserveScroll: true })}
          >
            {Object.entries(props.versiones).map(([v, nombre]) => (
              <option key={v} value={v}>{nombre}{props.cargas[props.anio]?.[v] ? '' : ' (sin datos)'}</option>
            ))}
          </select>
        </div>
        )}

        <nav className="flex flex-col gap-4">
          {menu.map(([grupo, items]) => (
            <div key={grupo} className="flex flex-col gap-0.5">
              <span className="side-titulo">{grupo}</span>
              {items.map(([href, label]) => (
                <Link
                  key={href}
                  href={href}
                  aria-current={activa(href) ? 'page' : undefined}
                  className={'side-link ' + (activa(href) ? 'side-link-activo' : '')}
                >
                  <span className="side-punto" />
                  {label}
                </Link>
              ))}
            </div>
          ))}
        </nav>

        {props.usuario && (
          <div className="side-pie">
            <span className="side-avatar">
              {props.usuario.nombre.split(' ').filter(Boolean).slice(0, 2).map((p) => p[0]).join('').toUpperCase()}
            </span>
            <span className="min-w-0 flex-1 truncate" title={props.usuario.email ?? ''}>{props.usuario.nombre}</span>
            <button className="side-btn" onClick={() => router.post('/logout')}>Salir</button>
          </div>
        )}
        <span className="-mt-2 text-[10.5px] leading-snug" style={{ color: 'var(--s-ink-3)' }}>
          Facultad de Economía y Negocios · Universidad de Chile
        </span>
      </aside>

      <main className="mx-auto max-w-[1480px] px-[max(16px,env(safe-area-inset-left))] pb-10 pt-5">{children}</main>

      {toast && (
        <div
          className="fixed bottom-5 left-1/2 z-30 flex max-w-[calc(100%-32px)] -translate-x-1/2 items-center gap-3 rounded-lg bg-ink px-3.5 py-2.5 text-bg"
          role="status"
        >
          <span>{toast.msg}</span>
          {toast.undo && (
            <button
              className="cursor-pointer font-semibold underline"
              onClick={() => {
                router.post(toast.undo, {}, { preserveScroll: true })
                setToast(null)
              }}
            >
              Deshacer
            </button>
          )}
        </div>
      )}
    </div>
  )
}
