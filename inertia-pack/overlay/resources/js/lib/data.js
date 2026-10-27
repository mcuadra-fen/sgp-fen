export const MESES = ['enero','febrero','marzo','abril','mayo','junio','julio','agosto','septiembre','octubre','noviembre','diciembre']
export const AB = ['ene','feb','mar','abr','may','jun','jul','ago','sep','oct','nov','dic']

const nf = new Intl.NumberFormat('es-CL')
export const fmt = (v) => nf.format(Math.round(v || 0))
export const fmtM = (v) =>
  v >= 1e6 ? (v / 1e6).toLocaleString('es-CL', { maximumFractionDigits: 1 }) + ' M' : fmt(v)
export const parse = (s) => {
  const n = parseInt(String(s).replace(/[^\d]/g, ''), 10)
  return Number.isNaN(n) ? 0 : n
}
export const sum = (a) => a.reduce((x, y) => x + y, 0)

/** Reparte `total` en enteros; el resto va de a 1 a los primeros meses del reparto (suma exacta). */
export function dist(total, modo, mes = 0) {
  const m = Array(12).fill(0)
  const spread = (idx) => {
    const b = Math.floor(total / idx.length)
    const r = total - b * idx.length
    idx.forEach((i, k) => { m[i] = b + (k < r ? 1 : 0) })
  }
  if (modo === 'trimestral') spread([0, 3, 6, 9])
  else if (modo === 'semestral') spread([0, 6])
  else if (modo === 'unico') m[mes] = total
  else spread([...Array(12).keys()])
  return m
}

export function niceMax(v) {
  if (v <= 0) return 1
  const p = Math.pow(10, Math.floor(Math.log10(v)))
  const n = v / p
  return (n <= 1 ? 1 : n <= 2 ? 2 : n <= 2.5 ? 2.5 : n <= 5 ? 5 : 10) * p
}

/** Token CSRF que Laravel deja en la cookie XSRF-TOKEN, para llamadas fetch. */
export function xsrf() {
  if (typeof document === 'undefined') return ''
  const m = document.cookie.match(/(?:^|;\s*)XSRF-TOKEN=([^;]+)/)
  return m ? decodeURIComponent(m[1]) : ''
}
