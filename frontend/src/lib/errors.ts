/** Extrae el primer mensaje de validación legible de un error de axios/Laravel. */
export function mensajeDeError(e: any, fallback: string): string {
  const errores = e?.response?.data?.errors
  const primerError = errores ? (Object.values(errores)[0] as string[])?.[0] : null
  return primerError ?? e?.response?.data?.message ?? fallback
}
