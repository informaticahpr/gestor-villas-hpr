import api from './api'

export type FormatoExportacion = 'excel' | 'pdf'

/** Abre en una pestaña nueva el recibo (PDF) de un movimiento; sirve tanto para el primero como para reimprimirlo. */
export function abrirRecibo(idMovimiento: number) {
  window.open(`${api.defaults.baseURL}/api/movimientos/${idMovimiento}/recibo`, '_blank')
}

/**
 * Abre en una pestaña nueva la exportacion de un reporte: el PDF se muestra en el
 * navegador y el Excel se descarga. Los parametros vacios no se envian.
 */
export function abrirReporte(
  reporte: 'estado-cuenta' | 'saldos-generales' | 'antiguedad-saldos' | 'bitacora',
  formato: FormatoExportacion,
  params: Record<string, string | boolean | undefined>,
) {
  const query = new URLSearchParams()
  for (const [clave, valor] of Object.entries(params)) {
    if (valor !== undefined && valor !== '') query.set(clave, String(valor))
  }
  const accion = formato === 'pdf' ? 'pdf' : 'exportar'
  // la bitacora vive en /api/bitacora (solo Director/Admin); los demas reportes en /api/reportes
  const ruta = reporte === 'bitacora' ? 'bitacora' : `reportes/${reporte}`
  window.open(`${api.defaults.baseURL}/api/${ruta}/${accion}?${query}`, '_blank')
}
