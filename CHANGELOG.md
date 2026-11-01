# Registro de cambios — SGP-FEN

Sistema de Gestión de Presupuesto - FEN (antes «Presupuesto UDT»).

Aquí se anotan los cambios visibles del sistema, del más reciente al más antiguo.
Formato basado en [Keep a Changelog](https://keepachangelog.com/es-ES/1.1.0/). Fechas en formato AAAA-MM-DD.

## [Sin publicar]

Cambios hechos después del último commit (`ec2d9aa`), aún no confirmados en git.

### Agregado

- **Cobros a áreas rediseñado** — 2026-10-31
  - La vista principal son las solicitudes «Traspaso (Cobros DEA)» con todos los campos de Webdox: descripción, estado,
    fechas, correlativo, solicitante, aprobador(a), CECO de ingreso y «Total de traspaso». Al expandir una fila se ven
    todos los campos del formulario y los adjuntos.
  - Cada solicitud tiene una página de detalle con el **resumen del Excel**: montos por área y por servicio, con el total.
    También tiene el botón «Descargar Excel» y sus comprobantes contables.
  - El Excel se toma de la carpeta compartida, porque la API de Webdox no permite descargar adjuntos.
    Hoy están los de abril a octubre de 2026.
  - La sincronización guarda los nombres de los adjuntos de cada solicitud.
- **Pagos a proveedores con la misma lógica** — 2026-10-31
  - La vista principal son las solicitudes con todos los campos de Webdox: correlativo, fecha del certificado, solicitante,
    aprobador(a), departamento, documentos, fecha de pago requerida y carpeta de antecedentes.
  - Cada solicitud enlaza a su página de detalle, con los campos de Webdox, las facturas del certificado de pago
    (neto, IVA, exento, total, cuenta, centro de costo) y el botón «Descargar Excel».
- **Mis cobros (acceso para las áreas)** — 2026-10-30
  - Las áreas pueden ver lo que la UDT les ha cobrado. Cada persona entra con su cuenta FEN y ve solo «Mis cobros», con los
    cobros de sus centros de costo agrupados por mes: concepto, comprobante, ID de Webdox y monto.
  - Nueva pantalla **Accesos de áreas** (solo administradores). Ahí se asignan centros de costo a un correo FEN, y la persona
    no necesita haber ingresado antes. El botón «Ver como esta persona» muestra lo que vería esa persona.
- **Ingreso con la cuenta FEN (Keycloak)** — 2026-10-29
  - Todo el sistema exige ingresar. `/login` lleva directo al SSO de la FEN (`sso-dev.fen.uchile.cl`, realm `FenUChile`, cliente `SGP-local`).
  - «Salir» cierra la sesión también en Keycloak y vuelve a su página de ingreso. Requiere registrar en Keycloak
    `…/signout-callback-oidc` como URI de redirección posterior al cierre y completar `KEYCLOAK_POST_LOGOUT_REDIRECT_URI`.
  - Los usuarios se crean o actualizan al ingresar, a partir de los datos de Keycloak.
- **Conexión con Webdox** — 2026-10-30
  - Comandos `webdox:probar`, `webdox:documentos`, `webdox:solicitudes` y `webdox:sincronizar [--todo]`.
  - Solo lectura: el sistema no modifica nada en Webdox.
- **Pagos a proveedores** — 2026-10-30
  - Sincroniza las solicitudes de Webdox con formulario «Pago a proveedores» de la UDT.
  - Lee los Excel `ID####_Certificado de Pago.xlsx` de la carpeta compartida (`WEBDOX_CERTIFICADOS_DIR`) y guarda una línea por factura.
    Cada factura queda unida a su solicitud por el ID de Webdox.
  - Botón «Leer certificados» y comando `certificados:importar`. Solo se vuelven a leer los archivos nuevos o modificados.
  - Vista por factura o agrupada por proveedor, y vista de solicitudes con su certificado.
- **Presupuesto vs. pagado (Ejecución)** — 2026-10-30
  - Cruza las facturas pagadas con el presupuesto usando la cuenta contable.
  - Separa lo pagado con centro de costo de la UDT y lo pagado con otros centros de costo o prorrateo.
  - Muestra saldo y % ejecutado, y el detalle de facturas de cada cuenta.
  - Lista aparte los pagos cuya cuenta no está en el presupuesto, como los de cuenta «VARIOS».
- **Cobros a áreas** — 2026-10-30
  - Muestra lo que la UDT cobra a otras áreas: solicitudes de Webdox con formulario «Traspaso (Cobros DEA)» de la UDT.
  - Los montos se leen de los comprobantes contables `T2026…pdf` de la carpeta «Traspaso de recursos entre centros de costos».
    Si una solicitud aún no tiene comprobante, se usan los asientos `.txt`.
    Cada comprobante se valida: los cargos deben sumar lo mismo que los abonos.
  - Los nombres de los centros de costo salen del tarifario de traspasos fijos o, si no están ahí, de la solicitud de Webdox.
  - Vista agrupada por área o por comprobante.
  - Botón «Leer comprobantes» y comando `traspasos:importar`.
- **Revisión de datos**: cada alerta del Resumen tiene un enlace «Ver →» que abre en Presupuesto las partidas afectadas.

### Cambiado

- **Acceso restringido** — 2026-10-30
  - Solo entran los administradores (`SGP_ADMINS`, hoy solo mcuadra@fen.uchile.cl) y las personas con centros de costo asignados.
  - Cualquier otra cuenta FEN es rechazada al ingresar, con un aviso.
- **Menú lateral** — 2026-10-30
  - El menú pasó de pestañas superiores a una barra lateral izquierda azul marino con acentos dorados.
  - Agrupa las opciones en Presupuesto, Ejecución y Datos. Incluye el año, la versión y el usuario.
  - En pantallas angostas se abre con el botón ☰. No aparece al imprimir.
- Nombres en el menú: «Ejecución» → «Presupuesto vs. pagado» y «Pagos» → «Pagos a proveedores».
- El sistema pasó a llamarse **SGP-FEN · Sistema de Gestión de Presupuesto - FEN**.
- Clasificar muestra la **Subactividad** en una columna propia.

### Corregido

- Ejecución: los pagos con centro de costo de otra unidad quedaban fuera aunque su cuenta estuviera en el presupuesto.
  Ahora el cruce se hace por cuenta.
- Ejecución: los totales mostraban 0 cuando el año elegido no tenía pagos.
  Ahora consideran todo el presupuesto y la pantalla avisa que no hay certificados de ese año.

### Técnico

- Nuevas dependencias: `laravel/socialite` y `socialiteproviders/keycloak` para el ingreso, y `smalot/pdfparser` para leer los comprobantes PDF.
- Nuevas migraciones:
  - `users`: columnas `keycloak_id` y `username`.
  - Tablas `pagos_webdox` (con columna `tipo`: pago | traspaso), `certificados_pago`, `lineas_pago`, `traspasos`,
    `lineas_traspaso`, `centros_costo` y `accesos_area`.
- Nuevas variables en `.env`: `KEYCLOAK_*`, `WEBDOX_*` y `SGP_ADMINS`, incluidas `WEBDOX_CERTIFICADOS_DIR` y `WEBDOX_TRASPASOS_FORMULARIO`.
  Las claves solo van en `.env`, que no se sube a git.
- Respaldos de la base antes de cada migración grande: `database/database.sqlite.bak-antes-*`.

## [0.2.0] — 2026-10-27 · `ec2d9aa`

### Agregado

- **Versiones del presupuesto**: «Presupuesto inicial» y «Proyección».
  - La proyección se importa desde el Excel de julio a diciembre. Enero a junio se puede completar con los montos del presupuesto inicial.
  - Selector de versión junto al año, con la marca «(sin datos)» si esa versión no está cargada.
  - El Informe compara cualquier combinación de año y versión, por ejemplo «Proyección Cierre» contra «Presupuesto».
  - Importar y exportar muestra los años cargados con sus versiones. El archivo exportado de una proyección lleva `-proyeccion` en el nombre.

## [0.1.0] — 2026-10-27 · `ec03e25`

### Agregado

- Carga inicial del sistema (Laravel + Inertia/React + SQLite):
  - Resumen, Presupuesto (partidas mensuales), Clasificar (categorías y observaciones), Informe e Importar y exportar.
  - Varios años, con eliminación y restauración de un año completo.
