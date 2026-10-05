export interface VillaResumen {
  villa: string
  nombre_completo: string
  saldo: number
  aplicobro: boolean
  cuota_especial: boolean
  monto_cuota_especial: number | null
}

/** Datos de una persona: los del propietario y los del encargado tienen la misma forma. */
export interface DatosPersona {
  NOMBRES: string | null
  APELLIDOS: string | null
  /** DNI o pasaporte */
  DNI: string | null
  /** Celular 1 */
  TELF: string | null
  /** Celular 2 */
  CELULAR: string | null
  OTRO_TEL: string | null
  MAIL: string | null
  MAIL2: string | null
  /** AAAA-MM-DD */
  FECHA_NAC: string | null
  /** Solo el encargado: parentesco o vinculo con el propietario */
  PARENTESCO?: string | null
}

export interface Propietario extends DatosPersona {
  id: number
  nombre_completo: string
  /** Numeros de las villas que tiene este propietario */
  villas: string[]
}

export interface DatosVilla {
  /** Ubicacion, ej. "BLOQUE A" */
  DIR: string | null
  /** Fecha de entrega, AAAA-MM-DD */
  FCONTRUC: string | null
  /** Medidor ENEE */
  NOMED: string | null
  CLAVE_CATASTRAL: string | null
  DESCRIPCION_IP: string | null
  /** Observacion libre (ej. a nombre de quienes esta la villa) */
  OBSERVACION: string | null
  NOHAB: number | null
  NOBATH: number | null
  APLICOBRO: boolean
  CUOTA_ESPECIAL: boolean
  MONTO_CUOTA_ESPECIAL: number | null
}

/** Propietario o encargado anterior: sus datos tal como estaban cuando dejo de serlo. */
export interface PersonaAnterior extends DatosPersona {
  id: number
  tipo: 'propietario' | 'encargado'
  nombre_completo: string
  /** AAAA-MM-DD; null si no se sabe desde cuando */
  desde: string | null
  /** AAAA-MM-DD: fecha del cambio */
  hasta: string
  /** Usuario que hizo el cambio */
  registrado_por: string | null
}

/** Pestaña "Historial de Villa" */
export interface HistorialVilla {
  propietario_desde: string | null
  encargado_desde: string | null
  propietarios: PersonaAnterior[]
  encargados: PersonaAnterior[]
}

export interface VillaDetalle {
  CLV_CLIE: string
  villa: DatosVilla
  propietario: Propietario | null
  /** null si la villa no tiene encargado */
  encargado: DatosPersona | null
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

export type EntidadBitacora = 'usuario' | 'concepto' | 'forma_pago' | 'cuota_especial' | 'cuota_mantenimiento' | 'movimiento'
export type AccionBitacora = 'crear' | 'editar' | 'activar' | 'desactivar' | 'eliminar' | 'anular'

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
  /** Anulado por Director/Admin: no cuenta en saldos ni sale en el estado de cuenta. */
  anulado: boolean
  anulado_en: string | null
  anulado_por: string | null
  motivo_anulacion: string | null
}

export interface RegistroBitacora {
  id: number
  usuario: string
  entidad: EntidadBitacora
  accion: AccionBitacora
  descripcion: string
  fecha: string
}
