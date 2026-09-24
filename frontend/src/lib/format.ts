/** Formatea un monto en dólares, ej. 32000 -> "$32,000.00", -1000 -> "-$1,000.00". */
export function formatearMonto(valor: number): string {
  const signo = valor < 0 ? '-' : ''
  const absoluto = Math.abs(valor)
  return `${signo}$${absoluto.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`
}
