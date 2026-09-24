export interface VillaResumen {
  villa: string
  nombre_completo: string
  saldo: number
  aplicobro: boolean
  cuota_especial: boolean
  monto_cuota_especial: number | null
}

export interface VillaDetalle {
  CLV_CLIE: string
  NOMBRES: string
  APELLIDOS: string
  DIR: string | null
  TELF: string | null
  CELULAR: string | null
  OTRO_TEL: string | null
  MAIL: string | null
  MAIL2: string | null
  FCONTRUC: string | null
  NOMED: string | null
  FECHA_NAC: string | null
  NOHAB: number | null
  NOBATH: number | null
  APLICOBRO: boolean
  CUOTA_ESPECIAL: boolean
  MONTO_CUOTA_ESPECIAL: number | null
  SALDO: number
}

export interface MovimientoFila {
  id: number
  folio: string | null
  tipo: 'cargo' | 'credito'
  fecha: string
  descripcion: string
  observacion: string | null
  cargo: number
  credito: number
  saldo: number
}

export interface EstadoCuenta {
  saldo_inicial: number
  movimientos: MovimientoFila[]
  saldo_final: number
}

export interface Concepto {
  NUM_CPTO: number
  DESCR: string
  ES_CARGO: boolean
  ACTIVO: boolean
  MONTO_DEFAULT: number | null
  /** El concepto de la cuota de mantenimiento mensual; su monto se define en Configuración → Cuotas. */
  ES_MANTENIMIENTO: boolean
}

export interface FormaPago {
  id: number
  nombre: string
  activo: boolean
}

export type EntidadBitacora = 'usuario' | 'concepto' | 'forma_pago' | 'cuota_especial' | 'cuota_mantenimiento'
export type AccionBitacora = 'crear' | 'editar' | 'activar' | 'desactivar' | 'eliminar'

export interface MetaPaginacion {
  pagina: number
  por_pagina: number
  total: number
  ultima_pagina: number
}

export interface MetaBitacora extends MetaPaginacion {
  /** Maximo de registros por archivo exportado. */
  limite_exportacion: number
}

/** Un movimiento (cargo o abono) tal como lo lista la pantalla de Reimpresión. */
export interface MovimientoListado {
  id: number
  folio: string | null
  /** AAAA-MM-DD */
  fecha: string
  villa: string
  propietario: string | null
  concepto: string
  tipo: 'cargo' | 'credito'
  importe: number
  forma_pago: string | null
  usuario: string
  observacion: string | null
}

export interface RegistroBitacora {
  id: number
  usuario: string
  entidad: EntidadBitacora
  accion: AccionBitacora
  descripcion: string
  fecha: string
}
