import '@fontsource/ibm-plex-sans/400.css'
import '@fontsource/ibm-plex-sans/500.css'
import '@fontsource/ibm-plex-sans/600.css'
import '@fontsource/ibm-plex-mono/400.css'
import '@fontsource/ibm-plex-mono/500.css'
import '../css/app.css'

import { createRoot } from 'react-dom/client'
import { createInertiaApp } from '@inertiajs/react'

const pages = import.meta.glob('./Pages/**/*.jsx', { eager: true })

createInertiaApp({
  title: (t) => (t ? `${t} · SGP-FEN` : 'SGP-FEN · Sistema de Gestión de Presupuesto'),
  resolve: (name) => {
    const page = pages[`./Pages/${name}.jsx`]
    if (!page) throw new Error(`Página no encontrada: ${name}`)
    return page
  },
  setup({ el, App, props }) {
    createRoot(el).render(<App {...props} />)
  },
  progress: { color: '#1b4b8f' },
})
