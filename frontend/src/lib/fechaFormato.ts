/**
 * Formato de fechas del sistema: DD/MM/AAAA (y DD/MM/AAAA HH:MM con hora, en 24 h).
 * La API intercambia fechas como AAAA-MM-DD; estas funciones convierten entre ambos.
 * Son puras (sin red ni DOM), por eso viven aparte de `fecha.ts`.
 */

/** Zona horaria de la app; debe coincidir con APP_TIMEZONE del backend. */
export const ZONA = 'America/Tegucigalpa'

/** "2026-09-20" (o "2026-09-20T…" / "2026-09-20 00:00:00") -> "20/09/2026". Vacio si no hay fecha. */
export function formatearFecha(fecha: string | null | undefined): string {
  if (!fecha) return ''
  const [anio, mes, dia] = fecha.slice(0, 10).split('-')
  return `${dia}/${mes}/${anio}`
}

/** "2026-09-21T02:35:35+00:00" -> "20/09/2026 20:35" (hora de Tegucigalpa, 24 h). */
export function formatearFechaHora(iso: string | null | undefined): string {
  if (!iso) return ''
  const partes = new Intl.DateTimeFormat('es-HN', {
    timeZone: ZONA,
    day: '2-digit',
    month: '2-digit',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
    hourCycle: 'h23',
  }).formatToParts(new Date(iso))
  const valor = (tipo: string) => partes.find((p) => p.type === tipo)?.value ?? ''
  return `${valor('day')}/${valor('month')}/${valor('year')} ${valor('hour')}:${valor('minute')}`
}

/** Mientras se escribe: deja solo digitos y pone las barras solas. "2009" -> "20/09", "20092026" -> "20/09/2026". */
export function enmascararFecha(texto: string): string {
  const d = texto.replace(/\D/g, '').slice(0, 8)
  if (d.length <= 2) return d
  if (d.length <= 4) return `${d.slice(0, 2)}/${d.slice(2)}`
  return `${d.slice(0, 2)}/${d.slice(2, 4)}/${d.slice(4)}`
}

/**
 * "20/09/2026" -> "2026-09-20". Devuelve null si esta incompleta o no es una fecha real
 * (ej. 31/02/2026) o si el año esta fuera de 1900-2100.
 */
export function parsearFecha(texto: string): string | null {
  const m = /^(\d{2})\/(\d{2})\/(\d{4})$/.exec(texto)
  if (!m) return null
  const dia = Number(m[1])
  const mes = Number(m[2])
  const anio = Number(m[3])
  if (anio < 1900 || anio > 2100) return null
  const f = new Date(Date.UTC(anio, mes - 1, dia))
  if (f.getUTCFullYear() !== anio || f.getUTCMonth() !== mes - 1 || f.getUTCDate() !== dia) return null
  return `${m[3]}-${m[2]}-${m[1]}`
}
