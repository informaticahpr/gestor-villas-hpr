import api from './api'
import { ZONA } from './fechaFormato'

/**
 * Fecha de hoy (AAAA-MM-DD) en la zona de la app, con el reloj del navegador. Sirve para tener un
 * valor inmediato; la fuente de verdad es `hoyServidor()`.
 *
 * No usar `new Date().toISOString().slice(0, 10)`: eso da la fecha en UTC y, pasadas las 6 pm de
 * Tegucigalpa, ya es el dia siguiente.
 */
export function hoyLocal(): string {
  return new Intl.DateTimeFormat('en-CA', { timeZone: ZONA }).format(new Date())
}

/** Fecha de hoy segun el servidor (la que usan sus validaciones). Si falla la peticion, usa la local. */
export async function hoyServidor(): Promise<string> {
  try {
    const { data } = await api.get('/api/hoy')
    return data.hoy
  } catch {
    return hoyLocal()
  }
}

/** "2026-09-20" -> "2026-09-01" */
export function inicioDeMes(fecha: string): string {
  return `${fecha.slice(0, 7)}-01`
}
