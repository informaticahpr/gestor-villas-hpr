/**
 * Filtros que se aplican mientras se escribe en los formularios de villa/propietario/encargado.
 * Todo el texto se captura en mayusculas; los campos numericos no aceptan letras.
 */

/** Nombres y apellidos: solo letras (con acentos/eñe) y espacios, en mayusculas. */
export function soloLetras(valor: string): string {
  return valor.replace(/[^\p{L}\s]/gu, '').toUpperCase()
}

/** Telefonos: solo digitos y guion (formato 9897-2123). */
export function soloTelefono(valor: string): string {
  return valor.replace(/[^0-9-]/g, '')
}

/** Cantidades enteras (habitaciones, baños): solo digitos. */
export function soloDigitos(valor: string): string {
  return valor.replace(/\D/g, '')
}

/** Texto libre en mayusculas (ubicacion, DNI, medidor, clave catastral...). */
export function mayusculas(valor: string): string {
  return valor.toUpperCase()
}

/** Correos: sin espacios y en minusculas. */
export function correo(valor: string): string {
  return valor.replace(/\s/g, '').toLowerCase()
}
