import { useMemo, useState } from 'react'
import { Link, router, useForm } from '@inertiajs/react'
import AppLayout from '../Layouts/AppLayout.jsx'

const th = 'h-10 whitespace-nowrap border-b border-lineStrong bg-surface2 px-2.5 text-left text-[11px] font-semibold uppercase tracking-wider text-ink2'
const td = 'border-b border-line px-2.5 py-2 align-top'
const cc = (c) => (c?.length === 15 ? `${c.slice(0, 8)}.${c.slice(8, 12)}.${c.slice(12)}` : c)

/** Asignación de centros de costo a los correos de las áreas, para «Mis cobros». */
export default function Accesos({ accesos, centros, admins }) {
  const form = useForm({ email: '', centros: [] })
  const [buscar, setBuscar] = useState('')

  const opciones = useMemo(() => {
    const t = buscar.toLowerCase()
    return centros.filter((c) => !t || [c.nombre, c.area, c.codigo].join(' ').toLowerCase().includes(t))
  }, [centros, buscar])
  const porCorreo = useMemo(() => {
    const m = new Map()
    for (const a of accesos) m.set(a.email, [...(m.get(a.email) ?? []), a])
    return [...m.entries()]
  }, [accesos])
  const marcar = (codigo) => form.setData('centros', form.data.centros.includes(codigo)
    ? form.data.centros.filter((c) => c !== codigo) : [...form.data.centros, codigo])
  const enviar = (e) => {
    e.preventDefault()
    form.post('/accesos', { preserveScroll: true, onSuccess: () => form.reset() })
  }

  return (
    <section className="grid gap-4 lg:grid-cols-[380px_1fr]">
      <form className="panel flex flex-col gap-3 self-start" onSubmit={enviar}>
        <h2 className="panel-title !mb-0">Asignar área a una persona</h2>
        <div className="flex flex-col gap-1">
          <label className="lbl" htmlFor="email">Correo FEN</label>
          <input id="email" type="email" className="inp" placeholder="nombre@fen.uchile.cl" value={form.data.email}
            onChange={(e) => form.setData('email', e.target.value)} />
          {form.errors.email && <span className="text-xs text-bad">{form.errors.email}</span>}
        </div>
        <div className="flex flex-col gap-1">
          <label className="lbl" htmlFor="buscar">Centros de costo que podrá ver ({form.data.centros.length} elegidos)</label>
          <input id="buscar" className="inp" placeholder="Buscar área o código…" value={buscar} onChange={(e) => setBuscar(e.target.value)} />
          <div className="max-h-[320px] overflow-y-auto rounded-md border border-line">
            {opciones.map((c) => (
              <label key={c.codigo} className="flex cursor-pointer items-start gap-2 border-b border-line px-2.5 py-1.5 last:border-0 hover:bg-surface2">
                <input type="checkbox" className="mt-0.5" checked={form.data.centros.includes(c.codigo)} onChange={() => marcar(c.codigo)} />
                <span className="min-w-0">
                  <span className="block">{c.nombre ?? 'Sin nombre'}</span>
                  <span className="block text-[11px] text-ink3">{c.area && c.area !== c.nombre ? `${c.area} · ` : ''}<span className="font-mono">{cc(c.codigo)}</span></span>
                </span>
              </label>
            ))}
          </div>
          {form.errors.centros && <span className="text-xs text-bad">{form.errors.centros}</span>}
        </div>
        <button className="btn btn-primary" disabled={form.processing}>Asignar</button>
        <p className="m-0 text-xs text-ink3">
          La persona entra con su cuenta FEN y solo ve «Mis cobros» con estos centros de costo. No necesita haber ingresado antes.
          Administradores (ven todo el sistema): {admins.join(', ')}.
        </p>
      </form>

      <div className="panel overflow-auto !p-0">
        <table className="w-full min-w-[640px] border-collapse text-sm">
          <thead><tr>
            <th className={th}>Persona</th><th className={th}>Centros de costo</th><th className={th} />
          </tr></thead>
          <tbody>
            {porCorreo.map(([email, as]) => (
              <tr key={email}>
                <td className={td}>
                  <div className="font-medium">{as[0].persona ?? email}</div>
                  {as[0].persona && <div className="text-xs text-ink3">{email}</div>}
                  {!as[0].persona && <div className="text-xs text-ink3">Aún no ha ingresado</div>}
                </td>
                <td className={td}>
                  <div className="flex flex-wrap gap-1.5">
                    {as.map((a) => (
                      <span key={a.id} className="pill inline-flex items-center gap-1.5">
                        {a.nombre ?? cc(a.centro_costo)}
                        <button type="button" className="cursor-pointer font-bold" aria-label={`Quitar ${a.nombre ?? a.centro_costo}`}
                          onClick={() => router.delete(`/accesos/${a.id}`, { preserveScroll: true })}>×</button>
                      </span>
                    ))}
                  </div>
                </td>
                <td className={td + ' text-right'}>
                  <Link className="btn btn-sm" href={`/mis-cobros?email=${encodeURIComponent(email)}`}>Ver como esta persona</Link>
                </td>
              </tr>
            ))}
            {!porCorreo.length && <tr><td colSpan={3} className={td + ' text-center text-ink3'}>Todavía no hay personas asignadas.</td></tr>}
          </tbody>
        </table>
      </div>
    </section>
  )
}

Accesos.layout = (page) => <AppLayout>{page}</AppLayout>
