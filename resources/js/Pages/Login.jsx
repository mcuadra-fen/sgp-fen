import { usePage } from '@inertiajs/react'

/** Pantalla de ingreso: el usuario y la clave se piden en el SSO de la FEN (Keycloak), no aquí. */
export default function Login() {
  const { flash } = usePage().props

  return (
    <main className="grid min-h-screen place-items-center px-4">
      <div className="panel flex w-full max-w-[380px] flex-col gap-4 text-center">
        <div>
          <b className="text-xl font-semibold tracking-tight">SGP-FEN</b>
          <p className="m-0 mt-1 text-[13px] text-ink2">Sistema de Gestión de Presupuesto - FEN</p>
          <p className="m-0 text-xs text-ink3">Facultad de Economía y Negocios · Universidad de Chile</p>
        </div>
        {flash?.error && <div className="rounded-md bg-badSoft px-3 py-2 text-[13px] text-bad" role="alert">{flash.error}</div>}
        {flash?.ok && <div className="rounded-md bg-surface2 px-3 py-2 text-[13px] text-ok" role="status">{flash.ok}</div>}
        {/* Enlace normal (no Inertia): el navegador sale hacia Keycloak */}
        <a href="/login/keycloak" className="btn btn-primary justify-center">Ingresar con cuenta FEN</a>
        <p className="m-0 text-xs text-ink3">Se abrirá el inicio de sesión institucional (SSO FEN).</p>
      </div>
    </main>
  )
}
