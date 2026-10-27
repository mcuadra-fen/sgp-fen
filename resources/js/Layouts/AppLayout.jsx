import { useEffect, useState } from 'react'
import { Link, router, usePage } from '@inertiajs/react'

const TABS = [
  ['/resumen', 'Resumen'],
  ['/partidas', 'Presupuesto'],
  ['/clasificar', 'Clasificar'],
  ['/informe', 'Informe'],
  ['/datos', 'Importar y exportar'],
]

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

  return (
    <div className="px-[max(16px,env(safe-area-inset-left))] pb-10">
      <header className="no-print mx-auto flex max-w-[1480px] flex-wrap items-center gap-x-6 gap-y-3 pt-[18px]">
        <div className="flex flex-col">
          <b className="text-lg font-semibold tracking-tight">SGP-FEN <span className="font-normal text-ink2">· Sistema de Gestión de Presupuesto - FEN</span></b>
          <span className="text-xs text-ink3">
            Facultad de Economía y Negocios · Universidad de Chile · vista anual enero–diciembre
          </span>
        </div>
        <label className="flex items-center gap-2 text-ink2">
          <span className="text-xs">Año</span>
          <select
            className="inp !w-auto py-1.5 font-semibold text-ink"
            value={props.anio}
            onChange={(e) => router.post('/anio', { anio: e.target.value }, { preserveScroll: true })}
          >
            {anios.map((a) => <option key={a} value={a}>{a}</option>)}
          </select>
        </label>
        <nav className="ml-auto flex flex-wrap gap-1">
          {TABS.map(([href, label]) => {
            const activa = url === href || url.startsWith(href + '/') || url.startsWith(href + '?')
            return (
              <Link
                key={href}
                href={href}
                className={
                  'cursor-pointer rounded-md border px-3.5 py-2 font-medium ' +
                  (activa
                    ? 'border-line bg-surface text-ink shadow-[inset_0_-2px_0_var(--accent)]'
                    : 'border-transparent text-ink2 hover:bg-surface')
                }
              >
                {label}
              </Link>
            )
          })}
        </nav>
      </header>

      <main className="mx-auto mt-4 max-w-[1480px]">{children}</main>

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
