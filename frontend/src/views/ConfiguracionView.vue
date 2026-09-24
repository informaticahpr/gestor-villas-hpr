<script setup lang="ts">
import { ref, reactive, onMounted } from 'vue'
import api from '../lib/api'
import { useAuthStore } from '../stores/auth'
import { useToastStore } from '../stores/toast'
import { useDialogStore } from '../stores/dialog'
import { mensajeDeError } from '../lib/errors'
import { formatearMonto } from '../lib/format'
import { abrirReporte, type FormatoExportacion } from '../lib/exportar'
import ExportarBotones from '../components/ExportarBotones.vue'
import FechaInput from '../components/FechaInput.vue'
import { formatearFechaHora } from '../lib/fechaFormato'
import PaginacionControles from '../components/PaginacionControles.vue'
import type { VillaResumen, Concepto, FormaPago, RegistroBitacora, EntidadBitacora, AccionBitacora, MetaBitacora } from '../types'

interface Usuario {
  id: number
  name: string
  email: string
  rol: string
  activo: boolean
}

const auth = useAuthStore()
const toast = useToastStore()
const dialog = useDialogStore()

const tab = ref<'usuarios' | 'conceptos' | 'cuotas' | 'formas-pago' | 'bitacora'>('usuarios')

// --- iconos (outline, mismo estilo que ToastContainer.vue) ---
const iconEditar = 'M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5Z'
const iconEliminar = 'M4 7h16M9 7V4h6v3m-8 0 1 13a1 1 0 0 0 1 1h6a1 1 0 0 0 1-1l1-13M10 11v6M14 11v6'
const iconOjo = 'M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178ZM15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z'
const iconOjoTachado = 'M3 3l18 18M10.584 10.587a2 2 0 0 0 2.828 2.828M9.363 5.365A9.466 9.466 0 0 1 12 5c4.478 0 8.268 2.943 9.542 7a9.522 9.522 0 0 1-1.622 2.977M6.61 6.61C4.462 8.019 2.83 10.243 2 13c1.274 4.057 5.064 7 9.542 7a9.477 9.477 0 0 0 4.132-.937'
const iconLlave = 'M15.75 5.25a3 3 0 0 1 3 3m3 0a6 6 0 0 1-7.029 5.912c-.563-.097-1.159.026-1.563.43L10.5 17.25H8.25v2.25H6v2.25H2.25v-2.818c0-.597.237-1.17.659-1.591l6.499-6.499c.404-.404.527-1 .43-1.563A6 6 0 1 1 21.75 8.25Z'
const iconGuardar = 'M4.5 12.75l6 6 9-13.5'
const iconCancelar = 'M6 18L18 6M6 6l12 12'

// --- Usuarios ---
const usuarios = ref<Usuario[]>([])
const roles = ref<string[]>([])
const cargandoUsuarios = ref(false)
const guardandoUsuario = ref(false)
const mostrarFormUsuario = ref(false)

const formUsuario = reactive({
  name: '',
  email: '',
  password: '',
  rol: '',
})

const editandoUsuario = ref<number | null>(null)
const formEditUsuario = reactive({ name: '', email: '', rol: '' })
const accionEnCursoUsuario = ref<number | null>(null)

function ordenarUsuarios() {
  usuarios.value.sort((a, b) => a.name.localeCompare(b.name))
}

async function cargarUsuarios() {
  cargandoUsuarios.value = true
  try {
    const { data } = await api.get('/api/usuarios')
    usuarios.value = data.data
    roles.value = data.roles
    formUsuario.rol = formUsuario.rol || roles.value[0] || ''
  } finally {
    cargandoUsuarios.value = false
  }
}

async function crearUsuario() {
  guardandoUsuario.value = true
  try {
    const { data } = await api.post('/api/usuarios', formUsuario)
    usuarios.value.push(data.user)
    ordenarUsuarios()
    toast.success(`Usuario ${formUsuario.name} creado correctamente.`)
    formUsuario.name = ''
    formUsuario.email = ''
    formUsuario.password = ''
    mostrarFormUsuario.value = false
  } catch (e: any) {
    toast.error(mensajeDeError(e, 'No se pudo crear el usuario.'))
  } finally {
    guardandoUsuario.value = false
  }
}

function iniciarEdicionUsuario(u: Usuario) {
  editandoUsuario.value = u.id
  formEditUsuario.name = u.name
  formEditUsuario.email = u.email
  formEditUsuario.rol = u.rol
}

function cancelarEdicionUsuario() {
  editandoUsuario.value = null
}

async function guardarEdicionUsuario(u: Usuario) {
  accionEnCursoUsuario.value = u.id
  try {
    const { data } = await api.put(`/api/usuarios/${u.id}`, formEditUsuario)
    u.name = data.user.name
    u.email = data.user.email
    u.rol = data.user.rol
    ordenarUsuarios()
    toast.success('Usuario actualizado correctamente.')
    editandoUsuario.value = null
  } catch (e: any) {
    toast.error(mensajeDeError(e, 'No se pudo actualizar el usuario.'))
  } finally {
    accionEnCursoUsuario.value = null
  }
}

async function alternarActivoUsuario(u: Usuario) {
  accionEnCursoUsuario.value = u.id
  try {
    const { data } = await api.patch(`/api/usuarios/${u.id}/activo`, { activo: !u.activo })
    const eraActivo = u.activo
    u.activo = data.user.activo
    toast.success(`Usuario "${u.name}" ${eraActivo ? 'deshabilitado' : 'habilitado'}.`)
  } catch (e: any) {
    toast.error(mensajeDeError(e, 'No se pudo cambiar el estado del usuario.'))
  } finally {
    accionEnCursoUsuario.value = null
  }
}

async function cambiarPasswordUsuario(u: Usuario) {
  const password = await dialog.pedirPassword({
    titulo: 'Cambiar contraseña',
    mensaje: `Define la nueva contraseña para el usuario "${u.name}".`,
  })
  if (password === null) return
  accionEnCursoUsuario.value = u.id
  try {
    await api.patch(`/api/usuarios/${u.id}/password`, { password })
    toast.success(`Contraseña de "${u.name}" actualizada.`)
  } catch (e) {
    toast.error(mensajeDeError(e, 'No se pudo cambiar la contraseña.'))
  } finally {
    accionEnCursoUsuario.value = null
  }
}

async function eliminarUsuario(u: Usuario) {
  const ok = await dialog.confirmar({
    titulo: 'Eliminar usuario',
    mensaje: `¿Eliminar al usuario "${u.name}"? Ya no aparecerá en el sistema, pero su historial se conserva.`,
    textoConfirmar: 'Eliminar',
    peligro: true,
  })
  if (!ok) return
  accionEnCursoUsuario.value = u.id
  try {
    await api.delete(`/api/usuarios/${u.id}`)
    usuarios.value = usuarios.value.filter((x) => x.id !== u.id)
    toast.success(`Usuario "${u.name}" eliminado.`)
  } catch (e: any) {
    toast.error(mensajeDeError(e, 'No se pudo eliminar el usuario.'))
  } finally {
    accionEnCursoUsuario.value = null
  }
}

// --- Conceptos ---
const conceptos = ref<Concepto[]>([])
const cargandoConceptos = ref(false)
const guardandoConcepto = ref(false)
const mostrarFormConcepto = ref(false)

const formConcepto = reactive({
  DESCR: '',
  ES_CARGO: true,
  MONTO_DEFAULT: null as number | null,
})

const editandoConcepto = ref<number | null>(null)
const formEditConcepto = reactive({ DESCR: '', ES_CARGO: true, MONTO_DEFAULT: null as number | null })
const accionEnCursoConcepto = ref<number | null>(null)

function ordenarConceptos() {
  conceptos.value.sort((a, b) => a.DESCR.localeCompare(b.DESCR))
}

async function cargarConceptos() {
  cargandoConceptos.value = true
  try {
    const { data } = await api.get('/api/conceptos', { params: { incluir_inactivos: true } })
    conceptos.value = data.data
  } finally {
    cargandoConceptos.value = false
  }
}

async function crearConcepto() {
  guardandoConcepto.value = true
  try {
    const { data } = await api.post('/api/conceptos', formConcepto)
    conceptos.value.push(data.concepto)
    ordenarConceptos()
    toast.success(`Concepto "${formConcepto.DESCR}" creado.`)
    formConcepto.DESCR = ''
    formConcepto.MONTO_DEFAULT = null
    mostrarFormConcepto.value = false
  } catch (e: any) {
    toast.error(mensajeDeError(e, 'No se pudo crear el concepto.'))
  } finally {
    guardandoConcepto.value = false
  }
}

function iniciarEdicionConcepto(c: Concepto) {
  editandoConcepto.value = c.NUM_CPTO
  formEditConcepto.DESCR = c.DESCR
  formEditConcepto.ES_CARGO = c.ES_CARGO
  formEditConcepto.MONTO_DEFAULT = c.MONTO_DEFAULT
}

function cancelarEdicionConcepto() {
  editandoConcepto.value = null
}

async function guardarEdicionConcepto(c: Concepto) {
  accionEnCursoConcepto.value = c.NUM_CPTO
  try {
    // el monto de la cuota de mantenimiento no se edita aqui, sino en Cuotas
    const payload = c.ES_MANTENIMIENTO
      ? { DESCR: formEditConcepto.DESCR, ES_CARGO: true }
      : formEditConcepto
    const { data } = await api.put(`/api/conceptos/${c.NUM_CPTO}`, payload)
    c.DESCR = data.concepto.DESCR
    c.ES_CARGO = data.concepto.ES_CARGO
    c.MONTO_DEFAULT = data.concepto.MONTO_DEFAULT
    ordenarConceptos()
    toast.success('Concepto actualizado correctamente.')
    editandoConcepto.value = null
  } catch (e: any) {
    toast.error(mensajeDeError(e, 'No se pudo actualizar el concepto.'))
  } finally {
    accionEnCursoConcepto.value = null
  }
}

async function alternarActivoConcepto(c: Concepto) {
  accionEnCursoConcepto.value = c.NUM_CPTO
  try {
    const { data } = await api.patch(`/api/conceptos/${c.NUM_CPTO}/activo`, { ACTIVO: !c.ACTIVO })
    const eraActivo = c.ACTIVO
    c.ACTIVO = data.concepto.ACTIVO
    toast.success(`Concepto "${c.DESCR}" ${eraActivo ? 'desactivado' : 'activado'}.`)
  } catch (e: any) {
    toast.error(mensajeDeError(e, 'No se pudo cambiar el estado del concepto.'))
  } finally {
    accionEnCursoConcepto.value = null
  }
}

async function eliminarConcepto(c: Concepto) {
  const ok = await dialog.confirmar({
    titulo: 'Eliminar concepto',
    mensaje: `¿Eliminar el concepto "${c.DESCR}"? Esta acción no se puede deshacer.`,
    textoConfirmar: 'Eliminar',
    peligro: true,
  })
  if (!ok) return
  accionEnCursoConcepto.value = c.NUM_CPTO
  try {
    await api.delete(`/api/conceptos/${c.NUM_CPTO}`)
    conceptos.value = conceptos.value.filter((x) => x.NUM_CPTO !== c.NUM_CPTO)
    toast.success(`Concepto "${c.DESCR}" eliminado.`)
  } catch (e: any) {
    toast.error(mensajeDeError(e, 'No se pudo eliminar el concepto.'))
  } finally {
    accionEnCursoConcepto.value = null
  }
}

// --- Cuotas: submenu "Cuota de mantenimiento" / "Cuotas especiales" ---
const subtabCuotas = ref<'mantenimiento' | 'especiales'>('mantenimiento')

// --- Cuota de mantenimiento (monto mensual que se cobra desde Cargo / Crédito) ---
const cuotaMantenimiento = ref<number | null>(null) // valor guardado
const montoMantenimiento = ref<number | null>(null) // valor que se esta escribiendo
const cargandoMantenimiento = ref(false)
const guardandoMantenimiento = ref(false)

async function cargarCuotaMantenimiento() {
  cargandoMantenimiento.value = true
  try {
    const { data } = await api.get('/api/cuota-mantenimiento')
    cuotaMantenimiento.value = data.MONTO
    montoMantenimiento.value = data.MONTO
  } catch (e: any) {
    toast.error(mensajeDeError(e, 'No se pudo cargar la cuota de mantenimiento.'))
  } finally {
    cargandoMantenimiento.value = false
  }
}

async function guardarCuotaMantenimiento() {
  guardandoMantenimiento.value = true
  try {
    const { data } = await api.put('/api/cuota-mantenimiento', { MONTO: montoMantenimiento.value })
    cuotaMantenimiento.value = data.MONTO
    montoMantenimiento.value = data.MONTO
    // la lista de conceptos (pestaña Conceptos) muestra el mismo monto
    const concepto = conceptos.value.find((c) => c.ES_MANTENIMIENTO)
    if (concepto) concepto.MONTO_DEFAULT = data.MONTO
    toast.success(`Cuota de mantenimiento actualizada a ${formatearMonto(data.MONTO)}.`)
  } catch (e: any) {
    toast.error(mensajeDeError(e, 'No se pudo guardar la cuota de mantenimiento.'))
  } finally {
    guardandoMantenimiento.value = false
  }
}

// --- Cuotas especiales ---
const villasCuotaEspecial = ref<VillaResumen[]>([])
const cargandoCuotas = ref(false)
const montosCuota = reactive<Record<string, number | null>>({})
const guardandoCuota = ref<string | null>(null)

async function cargarCuotasEspeciales() {
  cargandoCuotas.value = true
  try {
    const { data } = await api.get('/api/villas', { params: { cuota_especial: true } })
    villasCuotaEspecial.value = data.data
    for (const v of data.data as VillaResumen[]) {
      montosCuota[v.villa] = v.monto_cuota_especial
    }
  } finally {
    cargandoCuotas.value = false
  }
}

async function guardarCuotaEspecial(villa: string) {
  guardandoCuota.value = villa
  try {
    await api.patch(`/api/villas/${villa}/cuota-especial`, {
      MONTO_CUOTA_ESPECIAL: montosCuota[villa],
    })
    toast.success(`Cuota especial de la villa ${villa} actualizada.`)
  } catch (e: any) {
    toast.error(mensajeDeError(e, 'No se pudo guardar la cuota especial.'))
  } finally {
    guardandoCuota.value = null
  }
}

async function desactivarCuotaEspecial(villa: string) {
  guardandoCuota.value = villa
  try {
    await api.patch(`/api/villas/${villa}/cuota-especial`, { CUOTA_ESPECIAL: false })
    villasCuotaEspecial.value = villasCuotaEspecial.value.filter((v) => v.villa !== villa)
    toast.success(`Cuota especial desactivada para la villa ${villa}.`)
  } catch (e: any) {
    toast.error(mensajeDeError(e, 'No se pudo desactivar la cuota especial.'))
  } finally {
    guardandoCuota.value = null
  }
}

async function eliminarCuotaEspecial(villa: string) {
  const ok = await dialog.confirmar({
    titulo: 'Eliminar cuota especial',
    mensaje: `¿Eliminar la cuota especial de la villa ${villa}? Se borrará el monto asignado.`,
    textoConfirmar: 'Eliminar',
    peligro: true,
  })
  if (!ok) return
  guardandoCuota.value = villa
  try {
    await api.patch(`/api/villas/${villa}/cuota-especial`, { CUOTA_ESPECIAL: false, MONTO_CUOTA_ESPECIAL: null })
    villasCuotaEspecial.value = villasCuotaEspecial.value.filter((v) => v.villa !== villa)
    toast.success(`Cuota especial eliminada para la villa ${villa}.`)
  } catch (e: any) {
    toast.error(mensajeDeError(e, 'No se pudo eliminar la cuota especial.'))
  } finally {
    guardandoCuota.value = null
  }
}

// --- Formas de pago ---
const formasPago = ref<FormaPago[]>([])
const cargandoFormasPago = ref(false)
const guardandoFormaPago = ref(false)
const mostrarFormFormaPago = ref(false)
const nuevaFormaPago = ref('')

const editandoFormaPago = ref<number | null>(null)
const nombreEditFormaPago = ref('')
const accionEnCursoFormaPago = ref<number | null>(null)

function ordenarFormasPago() {
  formasPago.value.sort((a, b) => a.nombre.localeCompare(b.nombre))
}

async function cargarFormasPago() {
  cargandoFormasPago.value = true
  try {
    const { data } = await api.get('/api/formas-pago', { params: { incluir_inactivos: true } })
    formasPago.value = data.data
  } finally {
    cargandoFormasPago.value = false
  }
}

async function crearFormaPago() {
  if (!nuevaFormaPago.value.trim()) return
  guardandoFormaPago.value = true
  try {
    const { data } = await api.post('/api/formas-pago', { nombre: nuevaFormaPago.value.trim() })
    formasPago.value.push(data.forma_pago)
    ordenarFormasPago()
    toast.success(`Forma de pago "${nuevaFormaPago.value.trim()}" creada.`)
    nuevaFormaPago.value = ''
    mostrarFormFormaPago.value = false
  } catch (e: any) {
    toast.error(mensajeDeError(e, 'No se pudo crear la forma de pago.'))
  } finally {
    guardandoFormaPago.value = false
  }
}

function iniciarEdicionFormaPago(f: FormaPago) {
  editandoFormaPago.value = f.id
  nombreEditFormaPago.value = f.nombre
}

function cancelarEdicionFormaPago() {
  editandoFormaPago.value = null
}

async function guardarEdicionFormaPago(f: FormaPago) {
  if (!nombreEditFormaPago.value.trim()) return
  accionEnCursoFormaPago.value = f.id
  try {
    const { data } = await api.put(`/api/formas-pago/${f.id}`, { nombre: nombreEditFormaPago.value.trim() })
    f.nombre = data.forma_pago.nombre
    ordenarFormasPago()
    toast.success('Forma de pago actualizada correctamente.')
    editandoFormaPago.value = null
  } catch (e: any) {
    toast.error(mensajeDeError(e, 'No se pudo actualizar la forma de pago.'))
  } finally {
    accionEnCursoFormaPago.value = null
  }
}

async function alternarActivoFormaPago(f: FormaPago) {
  accionEnCursoFormaPago.value = f.id
  try {
    const { data } = await api.patch(`/api/formas-pago/${f.id}/activo`, { activo: !f.activo })
    const eraActivo = f.activo
    f.activo = data.forma_pago.activo
    toast.success(`Forma de pago "${f.nombre}" ${eraActivo ? 'desactivada' : 'activada'}.`)
  } catch (e: any) {
    toast.error(mensajeDeError(e, 'No se pudo cambiar el estado de la forma de pago.'))
  } finally {
    accionEnCursoFormaPago.value = null
  }
}

async function eliminarFormaPago(f: FormaPago) {
  const ok = await dialog.confirmar({
    titulo: 'Eliminar forma de pago',
    mensaje: `¿Eliminar la forma de pago "${f.nombre}"? Esta acción no se puede deshacer.`,
    textoConfirmar: 'Eliminar',
    peligro: true,
  })
  if (!ok) return
  accionEnCursoFormaPago.value = f.id
  try {
    await api.delete(`/api/formas-pago/${f.id}`)
    formasPago.value = formasPago.value.filter((x) => x.id !== f.id)
    toast.success(`Forma de pago "${f.nombre}" eliminada.`)
  } catch (e: any) {
    toast.error(mensajeDeError(e, 'No se pudo eliminar la forma de pago.'))
  } finally {
    accionEnCursoFormaPago.value = null
  }
}

// --- Bitácora ---
const registros = ref<RegistroBitacora[]>([])
const cargandoBitacora = ref(false)

// paginacion (la hace el servidor)
const paginaBitacora = ref(1)
const porPaginaBitacora = ref(10)
const metaBitacora = ref<MetaBitacora>({ pagina: 1, por_pagina: 10, total: 0, ultima_pagina: 1, limite_exportacion: 0 })

const filtroEntidad = ref<EntidadBitacora | ''>('')
const filtroAccion = ref<AccionBitacora | ''>('')
const filtroTexto = ref('')
const filtroDesde = ref('')
const filtroHasta = ref('')

const ENTIDADES: Array<{ value: EntidadBitacora; label: string }> = [
  { value: 'usuario', label: 'Usuarios' },
  { value: 'concepto', label: 'Conceptos' },
  { value: 'forma_pago', label: 'Formas de pago' },
  { value: 'cuota_especial', label: 'Cuotas especiales' },
  { value: 'cuota_mantenimiento', label: 'Cuota de mantenimiento' },
]

const ACCIONES: Array<{ value: AccionBitacora; label: string }> = [
  { value: 'crear', label: 'Crear' },
  { value: 'editar', label: 'Editar' },
  { value: 'activar', label: 'Activar' },
  { value: 'desactivar', label: 'Desactivar' },
  { value: 'eliminar', label: 'Eliminar' },
]

function etiquetaEntidad(entidad: string): string {
  return ENTIDADES.find((e) => e.value === entidad)?.label ?? entidad
}

function colorAccion(accion: string): string {
  switch (accion) {
    case 'crear':
    case 'activar':
      return 'bg-emerald-100 text-emerald-800'
    case 'editar':
      return 'bg-brand-100 text-brand-800'
    case 'desactivar':
      return 'bg-gold-200/70 text-espresso-800'
    case 'eliminar':
      return 'bg-wine-100 text-wine-800'
    default:
      return 'bg-espresso-800/10 text-espresso-800/60'
  }
}

// Filtros "aplicados": lo que se ve en la tabla. Cambiar de pagina, de tamaño o exportar usa este
// resumen y no lo que haya escrito el usuario en el formulario sin pulsar "Filtrar".
type FiltrosBitacora = Record<'entidad' | 'accion' | 'q' | 'desde' | 'hasta', string | undefined>
const filtrosAplicadosBitacora = ref<FiltrosBitacora>({ entidad: undefined, accion: undefined, q: undefined, desde: undefined, hasta: undefined })

async function cargarBitacora() {
  cargandoBitacora.value = true
  try {
    const { data } = await api.get('/api/bitacora', {
      params: { ...filtrosAplicadosBitacora.value, pagina: paginaBitacora.value, por_pagina: porPaginaBitacora.value },
    })
    registros.value = data.data
    metaBitacora.value = data.meta

    // si ya no existe la pagina pedida (se borraron registros), vuelve a la ultima
    if (data.data.length === 0 && data.meta.total > 0 && paginaBitacora.value > data.meta.ultima_pagina) {
      paginaBitacora.value = data.meta.ultima_pagina
      await cargarBitacora()
    }
  } catch (e: any) {
    toast.error(mensajeDeError(e, 'No se pudo cargar la bitácora.'))
  } finally {
    cargandoBitacora.value = false
  }
}

function filtrarBitacora() {
  filtrosAplicadosBitacora.value = {
    entidad: filtroEntidad.value || undefined,
    accion: filtroAccion.value || undefined,
    q: filtroTexto.value || undefined,
    desde: filtroDesde.value || undefined,
    hasta: filtroHasta.value || undefined,
  }
  paginaBitacora.value = 1
  cargarBitacora()
}

function limpiarFiltrosBitacora() {
  filtroEntidad.value = ''
  filtroAccion.value = ''
  filtroTexto.value = ''
  filtroDesde.value = ''
  filtroHasta.value = ''
  filtrarBitacora()
}

function irAPaginaBitacora(pagina: number) {
  paginaBitacora.value = pagina
  cargarBitacora()
}

function cambiarPorPaginaBitacora(porPagina: number) {
  porPaginaBitacora.value = porPagina
  paginaBitacora.value = 1
  cargarBitacora()
}

// Exporta TODOS los registros que coinciden con el filtro aplicado (no solo la pagina en pantalla).
function exportarBitacora(formato: FormatoExportacion) {
  const { total, limite_exportacion: limite } = metaBitacora.value
  if (total === 0) {
    toast.warning('No hay registros para exportar con estos filtros.')
    return
  }
  if (total > limite) {
    toast.error(`Hay ${total} registros con estos filtros y el máximo por archivo es ${limite}. Acota el rango de fechas.`)
    return
  }
  abrirReporte('bitacora', formato, filtrosAplicadosBitacora.value)
}

onMounted(() => {
  cargarUsuarios()
  cargarConceptos()
  cargarCuotaMantenimiento()
  cargarCuotasEspeciales()
  cargarFormasPago()
  cargarBitacora()
})
</script>

<template>
  <div>
    <p class="mb-4 font-display text-2xl font-semibold text-espresso-800">Configuración</p>

    <div class="mb-6 flex flex-wrap gap-4 border-b border-gold-300/30 text-sm">
      <button
        class="border-b-2 px-1 pb-2 font-medium"
        :class="tab === 'usuarios' ? 'border-brand-600 text-brand-700' : 'border-transparent text-espresso-800/40'"
        @click="tab = 'usuarios'"
      >
        Usuarios
      </button>
      <button
        class="border-b-2 px-1 pb-2 font-medium"
        :class="tab === 'conceptos' ? 'border-brand-600 text-brand-700' : 'border-transparent text-espresso-800/40'"
        @click="tab = 'conceptos'"
      >
        Conceptos
      </button>
      <button
        class="border-b-2 px-1 pb-2 font-medium"
        :class="tab === 'cuotas' ? 'border-brand-600 text-brand-700' : 'border-transparent text-espresso-800/40'"
        @click="tab = 'cuotas'"
      >
        Cuotas
      </button>
      <button
        class="border-b-2 px-1 pb-2 font-medium"
        :class="tab === 'formas-pago' ? 'border-brand-600 text-brand-700' : 'border-transparent text-espresso-800/40'"
        @click="tab = 'formas-pago'"
      >
        Formas de pago
      </button>
      <button
        class="border-b-2 px-1 pb-2 font-medium"
        :class="tab === 'bitacora' ? 'border-brand-600 text-brand-700' : 'border-transparent text-espresso-800/40'"
        @click="tab = 'bitacora'"
      >
        Bitácora
      </button>
    </div>

    <!-- Usuarios -->
    <section v-if="tab === 'usuarios'">
      <div class="mb-5 flex items-center justify-between">
        <p class="font-display text-lg font-semibold text-espresso-800">Usuarios</p>
        <!-- solo el Administrador gestiona usuarios; el Director los ve en modo lectura -->
        <button
          v-if="auth.esAdmin()"
          class="rounded-lg bg-gradient-to-r from-wine-500 via-brand-500 to-gold-500 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:opacity-90"
          @click="mostrarFormUsuario = !mostrarFormUsuario"
        >
          {{ mostrarFormUsuario ? 'Cancelar' : 'Nuevo usuario' }}
        </button>
        <span v-else class="rounded-full bg-espresso-800/10 px-3 py-1 text-xs font-medium text-espresso-800/60">
          Solo lectura: únicamente el Administrador puede modificar usuarios
        </span>
      </div>

      <form v-if="mostrarFormUsuario && auth.esAdmin()" class="mb-6 grid grid-cols-2 gap-4 rounded-xl border border-gold-300/30 bg-cream-50 p-5 shadow-sm" @submit.prevent="crearUsuario">
        <div>
          <label class="mb-1 block text-sm font-medium text-espresso-700">Nombre</label>
          <input v-model="formUsuario.name" required class="w-full rounded-lg border border-espresso-800/15 bg-white px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-200" />
        </div>
        <div>
          <label class="mb-1 block text-sm font-medium text-espresso-700">Correo</label>
          <input v-model="formUsuario.email" type="email" required class="w-full rounded-lg border border-espresso-800/15 bg-white px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-200" />
        </div>
        <div>
          <label class="mb-1 block text-sm font-medium text-espresso-700">Contraseña</label>
          <input v-model="formUsuario.password" type="password" required minlength="8" class="w-full rounded-lg border border-espresso-800/15 bg-white px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-200" />
        </div>
        <div>
          <label class="mb-1 block text-sm font-medium text-espresso-700">Rol</label>
          <select v-model="formUsuario.rol" required class="w-full rounded-lg border border-espresso-800/15 bg-white px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-200">
            <option v-for="r in roles" :key="r" :value="r">{{ r }}</option>
          </select>
        </div>
        <div class="col-span-2">
          <button
            type="submit"
            :disabled="guardandoUsuario"
            class="rounded-lg bg-gradient-to-r from-wine-500 via-brand-500 to-gold-500 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:opacity-90 disabled:opacity-50"
          >
            {{ guardandoUsuario ? 'Creando...' : 'Crear usuario' }}
          </button>
        </div>
      </form>

      <div class="overflow-hidden rounded-xl border border-gold-300/30 bg-cream-50 shadow-sm">
        <table class="min-w-full divide-y divide-gold-300/20 text-sm">
          <thead class="bg-brand-50/60">
            <tr>
              <th class="px-4 py-2.5 text-left font-medium text-espresso-800/70">Nombre</th>
              <th class="px-4 py-2.5 text-left font-medium text-espresso-800/70">Correo</th>
              <th class="px-4 py-2.5 text-left font-medium text-espresso-800/70">Rol</th>
              <th class="px-4 py-2.5 text-left font-medium text-espresso-800/70">Estado</th>
              <th class="px-4 py-2.5"></th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gold-300/15">
            <tr v-for="u in usuarios" :key="u.id" class="hover:bg-brand-50/40">
              <template v-if="editandoUsuario === u.id">
                <td class="px-4 py-2.5">
                  <input v-model="formEditUsuario.name" required class="w-full rounded-lg border border-espresso-800/15 bg-white px-2.5 py-1.5 text-sm focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-200" />
                </td>
                <td class="px-4 py-2.5">
                  <input v-model="formEditUsuario.email" type="email" required class="w-full rounded-lg border border-espresso-800/15 bg-white px-2.5 py-1.5 text-sm focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-200" />
                </td>
                <td class="px-4 py-2.5">
                  <select v-model="formEditUsuario.rol" required class="w-full rounded-lg border border-espresso-800/15 bg-white px-2.5 py-1.5 text-sm focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-200">
                    <option v-for="r in roles" :key="r" :value="r">{{ r }}</option>
                  </select>
                </td>
                <td class="px-4 py-2.5"></td>
                <td class="px-4 py-2.5">
                  <div class="flex justify-end gap-1">
                    <button type="button" title="Guardar" :disabled="accionEnCursoUsuario === u.id" class="rounded-lg p-1.5 text-brand-700 hover:bg-brand-50 disabled:opacity-40" @click="guardarEdicionUsuario(u)">
                      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="h-4 w-4"><path stroke-linecap="round" stroke-linejoin="round" :d="iconGuardar" /></svg>
                    </button>
                    <button type="button" title="Cancelar" :disabled="accionEnCursoUsuario === u.id" class="rounded-lg p-1.5 text-espresso-700/60 hover:bg-espresso-800/5 hover:text-espresso-700 disabled:opacity-40" @click="cancelarEdicionUsuario">
                      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="h-4 w-4"><path stroke-linecap="round" stroke-linejoin="round" :d="iconCancelar" /></svg>
                    </button>
                  </div>
                </td>
              </template>
              <template v-else>
                <td class="px-4 py-2.5 font-medium text-espresso-900">{{ u.name }}</td>
                <td class="px-4 py-2.5 text-espresso-800/80">{{ u.email }}</td>
                <td class="px-4 py-2.5">
                  <span class="rounded-full bg-brand-100 px-2.5 py-0.5 text-xs font-medium text-brand-800">{{ u.rol }}</span>
                </td>
                <td class="px-4 py-2.5">
                  <span class="rounded-full px-2.5 py-0.5 text-xs font-medium" :class="u.activo ? 'bg-brand-100 text-brand-800' : 'bg-espresso-800/10 text-espresso-800/50'">
                    {{ u.activo ? 'Activo' : 'Inactivo' }}
                  </span>
                </td>
                <td class="px-4 py-2.5">
                  <div v-if="auth.esAdmin()" class="flex justify-end gap-1">
                    <button type="button" title="Editar" :disabled="accionEnCursoUsuario === u.id" class="rounded-lg p-1.5 text-brand-700 hover:bg-brand-50 disabled:opacity-40" @click="iniciarEdicionUsuario(u)">
                      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="h-4 w-4"><path stroke-linecap="round" stroke-linejoin="round" :d="iconEditar" /></svg>
                    </button>
                    <button
                      type="button"
                      title="Cambiar contraseña"
                      :disabled="accionEnCursoUsuario === u.id"
                      class="rounded-lg p-1.5 text-espresso-700/70 hover:bg-espresso-800/5 hover:text-espresso-800 disabled:opacity-40"
                      @click="cambiarPasswordUsuario(u)"
                    >
                      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="h-4 w-4"><path stroke-linecap="round" stroke-linejoin="round" :d="iconLlave" /></svg>
                    </button>
                    <button
                      type="button"
                      :title="u.id === auth.user?.id ? 'No puedes deshabilitar tu propia cuenta' : (u.activo ? 'Deshabilitar' : 'Habilitar')"
                      :disabled="accionEnCursoUsuario === u.id || (u.activo && u.id === auth.user?.id)"
                      class="rounded-lg p-1.5 text-espresso-700/70 hover:bg-espresso-800/5 hover:text-espresso-800 disabled:opacity-40"
                      @click="alternarActivoUsuario(u)"
                    >
                      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="h-4 w-4"><path stroke-linecap="round" stroke-linejoin="round" :d="u.activo ? iconOjoTachado : iconOjo" /></svg>
                    </button>
                    <button
                      type="button"
                      :title="u.activo ? 'Debes deshabilitarlo primero para poder eliminarlo' : 'Eliminar'"
                      :disabled="accionEnCursoUsuario === u.id || u.activo"
                      class="rounded-lg p-1.5 text-wine-700 hover:bg-wine-50 disabled:opacity-40"
                      @click="eliminarUsuario(u)"
                    >
                      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="h-4 w-4"><path stroke-linecap="round" stroke-linejoin="round" :d="iconEliminar" /></svg>
                    </button>
                  </div>
                </td>
              </template>
            </tr>
            <tr v-if="!cargandoUsuarios && usuarios.length === 0">
              <td colspan="5" class="px-4 py-8 text-center text-espresso-800/40">Sin usuarios</td>
            </tr>
          </tbody>
        </table>
      </div>
    </section>

    <!-- Conceptos -->
    <section v-if="tab === 'conceptos'">
      <div class="mb-5 flex items-center justify-between">
        <p class="font-display text-lg font-semibold text-espresso-800">Conceptos</p>
        <button
          class="rounded-lg bg-gradient-to-r from-wine-500 via-brand-500 to-gold-500 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:opacity-90"
          @click="mostrarFormConcepto = !mostrarFormConcepto"
        >
          {{ mostrarFormConcepto ? 'Cancelar' : 'Nuevo concepto' }}
        </button>
      </div>

      <form v-if="mostrarFormConcepto" class="mb-6 grid grid-cols-2 gap-4 rounded-xl border border-gold-300/30 bg-cream-50 p-5 shadow-sm" @submit.prevent="crearConcepto">
        <div>
          <label class="mb-1 block text-sm font-medium text-espresso-700">Descripción</label>
          <input v-model="formConcepto.DESCR" required maxlength="40" class="w-full rounded-lg border border-espresso-800/15 bg-white px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-200" />
        </div>
        <div>
          <label class="mb-1 block text-sm font-medium text-espresso-700">Tipo</label>
          <select v-model="formConcepto.ES_CARGO" required class="w-full rounded-lg border border-espresso-800/15 bg-white px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-200">
            <option :value="true">Cargo</option>
            <option :value="false">Crédito / Abono</option>
          </select>
        </div>
        <div>
          <label class="mb-1 block text-sm font-medium text-espresso-700">Monto fijo (opcional)</label>
          <div class="relative">
            <span class="pointer-events-none absolute inset-y-0 left-3 flex items-center text-sm text-espresso-800/40">$</span>
            <input v-model.number="formConcepto.MONTO_DEFAULT" type="number" step="0.01" min="0" placeholder="Libre" class="w-full rounded-lg border border-espresso-800/15 bg-white py-2 pl-7 pr-3 text-sm focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-200" />
          </div>
          <p class="mt-1 text-xs text-espresso-800/50">Si lo defines, nadie podrá cambiar el valor al aplicar este concepto.</p>
        </div>
        <div class="col-span-2">
          <button
            type="submit"
            :disabled="guardandoConcepto"
            class="rounded-lg bg-gradient-to-r from-wine-500 via-brand-500 to-gold-500 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:opacity-90 disabled:opacity-50"
          >
            {{ guardandoConcepto ? 'Creando...' : 'Crear concepto' }}
          </button>
        </div>
      </form>

      <div class="overflow-hidden rounded-xl border border-gold-300/30 bg-cream-50 shadow-sm">
        <table class="min-w-full divide-y divide-gold-300/20 text-sm">
          <thead class="bg-brand-50/60">
            <tr>
              <th class="px-4 py-2.5 text-left font-medium text-espresso-800/70">Descripción</th>
              <th class="px-4 py-2.5 text-left font-medium text-espresso-800/70">Tipo</th>
              <th class="px-4 py-2.5 text-left font-medium text-espresso-800/70">Monto fijo</th>
              <th class="px-4 py-2.5 text-left font-medium text-espresso-800/70">Estado</th>
              <th class="px-4 py-2.5"></th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gold-300/15">
            <tr v-for="c in conceptos" :key="c.NUM_CPTO" class="hover:bg-brand-50/40">
              <template v-if="editandoConcepto === c.NUM_CPTO">
                <td class="px-4 py-2.5">
                  <input v-model="formEditConcepto.DESCR" required maxlength="40" class="w-full rounded-lg border border-espresso-800/15 bg-white px-2.5 py-1.5 text-sm focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-200" />
                </td>
                <td class="px-4 py-2.5">
                  <select v-model="formEditConcepto.ES_CARGO" :disabled="c.ES_MANTENIMIENTO" class="w-full rounded-lg border border-espresso-800/15 bg-white px-2.5 py-1.5 text-sm focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-200 disabled:bg-cream-200 disabled:text-espresso-800/60">
                    <option :value="true">Cargo</option>
                    <option :value="false">Crédito / Abono</option>
                  </select>
                </td>
                <td class="px-4 py-2.5">
                  <span v-if="c.ES_MANTENIMIENTO" class="text-xs text-espresso-800/50">Se define en Cuotas</span>
                  <div v-else class="relative w-28">
                    <span class="pointer-events-none absolute inset-y-0 left-2.5 flex items-center text-xs text-espresso-800/40">$</span>
                    <input v-model.number="formEditConcepto.MONTO_DEFAULT" type="number" step="0.01" min="0" placeholder="Libre" class="w-full rounded-lg border border-espresso-800/15 bg-white py-1.5 pl-6 pr-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-200" />
                  </div>
                </td>
                <td class="px-4 py-2.5"></td>
                <td class="px-4 py-2.5">
                  <div class="flex justify-end gap-1">
                    <button type="button" title="Guardar" :disabled="accionEnCursoConcepto === c.NUM_CPTO" class="rounded-lg p-1.5 text-brand-700 hover:bg-brand-50 disabled:opacity-40" @click="guardarEdicionConcepto(c)">
                      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="h-4 w-4"><path stroke-linecap="round" stroke-linejoin="round" :d="iconGuardar" /></svg>
                    </button>
                    <button type="button" title="Cancelar" :disabled="accionEnCursoConcepto === c.NUM_CPTO" class="rounded-lg p-1.5 text-espresso-700/60 hover:bg-espresso-800/5 hover:text-espresso-700 disabled:opacity-40" @click="cancelarEdicionConcepto">
                      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="h-4 w-4"><path stroke-linecap="round" stroke-linejoin="round" :d="iconCancelar" /></svg>
                    </button>
                  </div>
                </td>
              </template>
              <template v-else>
                <td class="px-4 py-2.5 font-medium text-espresso-900">
                  {{ c.DESCR }}
                  <span v-if="c.ES_MANTENIMIENTO" class="ml-2 rounded-full bg-gold-300/40 px-2 py-0.5 text-xs font-medium text-espresso-800">Cuota mensual</span>
                </td>
                <td class="px-4 py-2.5">
                  <span class="rounded-full px-2.5 py-0.5 text-xs font-medium" :class="c.ES_CARGO ? 'bg-wine-100 text-wine-800' : 'bg-emerald-100 text-emerald-800'">
                    {{ c.ES_CARGO ? 'Cargo' : 'Crédito' }}
                  </span>
                </td>
                <td class="px-4 py-2.5">
                  <span v-if="c.MONTO_DEFAULT !== null" class="font-medium text-espresso-900">{{ formatearMonto(c.MONTO_DEFAULT) }}</span>
                  <span v-else-if="c.ES_MANTENIMIENTO" class="text-wine-600">Sin definir — se define en Cuotas</span>
                  <span v-else class="text-espresso-800/40">Libre</span>
                </td>
                <td class="px-4 py-2.5">
                  <span class="rounded-full px-2.5 py-0.5 text-xs font-medium" :class="c.ACTIVO ? 'bg-brand-100 text-brand-800' : 'bg-espresso-800/10 text-espresso-800/50'">
                    {{ c.ACTIVO ? 'Activo' : 'Inactivo' }}
                  </span>
                </td>
                <td class="px-4 py-2.5">
                  <div class="flex justify-end gap-1">
                    <button type="button" title="Editar" :disabled="accionEnCursoConcepto === c.NUM_CPTO" class="rounded-lg p-1.5 text-brand-700 hover:bg-brand-50 disabled:opacity-40" @click="iniciarEdicionConcepto(c)">
                      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="h-4 w-4"><path stroke-linecap="round" stroke-linejoin="round" :d="iconEditar" /></svg>
                    </button>
                    <button type="button" :title="c.ES_MANTENIMIENTO ? 'La cuota de mantenimiento no se puede desactivar' : c.ACTIVO ? 'Desactivar' : 'Activar'" :disabled="accionEnCursoConcepto === c.NUM_CPTO || c.ES_MANTENIMIENTO" class="rounded-lg p-1.5 text-espresso-700/70 hover:bg-espresso-800/5 hover:text-espresso-800 disabled:opacity-40" @click="alternarActivoConcepto(c)">
                      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="h-4 w-4"><path stroke-linecap="round" stroke-linejoin="round" :d="c.ACTIVO ? iconOjoTachado : iconOjo" /></svg>
                    </button>
                    <button type="button" :title="c.ES_MANTENIMIENTO ? 'La cuota de mantenimiento no se puede eliminar' : 'Eliminar'" :disabled="accionEnCursoConcepto === c.NUM_CPTO || c.ES_MANTENIMIENTO" class="rounded-lg p-1.5 text-wine-700 hover:bg-wine-50 disabled:opacity-40" @click="eliminarConcepto(c)">
                      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="h-4 w-4"><path stroke-linecap="round" stroke-linejoin="round" :d="iconEliminar" /></svg>
                    </button>
                  </div>
                </td>
              </template>
            </tr>
            <tr v-if="!cargandoConceptos && conceptos.length === 0">
              <td colspan="5" class="px-4 py-8 text-center text-espresso-800/40">Sin conceptos</td>
            </tr>
          </tbody>
        </table>
      </div>
    </section>

    <!-- Cuotas: cuota de mantenimiento + cuotas especiales -->
    <section v-if="tab === 'cuotas'">
      <p class="mb-4 font-display text-lg font-semibold text-espresso-800">Cuotas</p>

      <div class="mb-5 flex flex-wrap gap-2">
        <button
          type="button"
          class="rounded-lg border px-3 py-1.5 text-sm font-medium"
          :class="subtabCuotas === 'mantenimiento' ? 'border-brand-600 bg-brand-50 text-brand-700' : 'border-espresso-800/20 text-espresso-700 hover:bg-brand-50'"
          @click="subtabCuotas = 'mantenimiento'"
        >
          Cuota de mantenimiento
        </button>
        <button
          type="button"
          class="rounded-lg border px-3 py-1.5 text-sm font-medium"
          :class="subtabCuotas === 'especiales' ? 'border-brand-600 bg-brand-50 text-brand-700' : 'border-espresso-800/20 text-espresso-700 hover:bg-brand-50'"
          @click="subtabCuotas = 'especiales'"
        >
          Cuotas especiales
        </button>
      </div>

      <!-- Cuota de mantenimiento -->
      <div v-if="subtabCuotas === 'mantenimiento'" class="max-w-xl">
        <p class="mb-4 text-sm text-espresso-800/60">
          Monto de la cuota mensual de mantenimiento. Se aplica desde Cargo / Crédito y los usuarios no pueden
          cambiarlo al aplicarla. Las villas con cuota especial pagan, en cambio, el monto asignado a cada una en
          "Cuotas especiales".
        </p>

        <form class="rounded-xl border border-gold-300/30 bg-cream-50 p-5 shadow-sm" @submit.prevent="guardarCuotaMantenimiento">
          <label class="mb-1.5 block text-sm font-medium text-espresso-700">Cuota mensual</label>
          <div class="flex flex-wrap items-center gap-3">
            <div class="relative w-44">
              <span class="pointer-events-none absolute inset-y-0 left-3 flex items-center text-sm text-espresso-800/40">$</span>
              <input
                v-model.number="montoMantenimiento"
                type="number"
                step="0.01"
                min="0.01"
                required
                :disabled="cargandoMantenimiento"
                class="w-full rounded-lg border border-espresso-800/15 bg-white py-2 pl-7 pr-3 text-sm focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-200 disabled:bg-cream-200"
              />
            </div>
            <button
              type="submit"
              :disabled="guardandoMantenimiento || cargandoMantenimiento || montoMantenimiento === cuotaMantenimiento"
              class="rounded-lg bg-gradient-to-r from-wine-500 via-brand-500 to-gold-500 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:opacity-90 disabled:opacity-50"
            >
              {{ guardandoMantenimiento ? 'Guardando...' : 'Guardar' }}
            </button>
          </div>

          <p
            v-if="!cargandoMantenimiento && cuotaMantenimiento === null"
            class="mt-4 rounded-lg border border-gold-300/40 bg-brand-50/60 px-3 py-2 text-sm text-wine-600"
          >
            La cuota mensual aún no está definida: no se podrá aplicar el cargo de mantenimiento hasta que la definas.
          </p>
          <p v-else-if="cuotaMantenimiento !== null" class="mt-4 text-sm text-espresso-800/60">
            Cuota vigente: <strong class="text-espresso-900">{{ formatearMonto(cuotaMantenimiento) }}</strong> al mes.
            <span v-if="villasCuotaEspecial.length > 0">
              {{ villasCuotaEspecial.length }} villa{{ villasCuotaEspecial.length === 1 ? '' : 's' }} con cuota especial pagan su propio monto.
            </span>
          </p>
        </form>
      </div>

      <!-- Cuotas especiales -->
      <div v-else>
      <p class="mb-4 text-sm text-espresso-800/60">
        Villas marcadas con "Cuota especial" al crearlas o editarlas. Asigna el monto que se les cobrará al aplicar la
        cuota de mantenimiento, en lugar de la cuota mensual.
      </p>

      <div class="overflow-hidden rounded-xl border border-gold-300/30 bg-cream-50 shadow-sm">
        <table class="min-w-full divide-y divide-gold-300/20 text-sm">
          <thead class="bg-brand-50/60">
            <tr>
              <th class="px-4 py-2.5 text-left font-medium text-espresso-800/70">Villa</th>
              <th class="px-4 py-2.5 text-left font-medium text-espresso-800/70">Propietario</th>
              <th class="px-4 py-2.5 text-left font-medium text-espresso-800/70">Monto cuota especial</th>
              <th class="px-4 py-2.5"></th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gold-300/15">
            <tr v-for="v in villasCuotaEspecial" :key="v.villa" class="hover:bg-brand-50/40">
              <td class="px-4 py-2.5 font-medium text-espresso-900">{{ v.villa }}</td>
              <td class="px-4 py-2.5 text-espresso-800/80">{{ v.nombre_completo }}</td>
              <td class="px-4 py-2.5">
                <div class="relative w-32">
                  <span class="pointer-events-none absolute inset-y-0 left-2.5 flex items-center text-xs text-espresso-800/40">$</span>
                  <input
                    v-model.number="montosCuota[v.villa]"
                    type="number" step="0.01" min="0"
                    class="w-full rounded-lg border border-espresso-800/15 bg-white py-1.5 pl-6 pr-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-200"
                  />
                </div>
              </td>
              <td class="px-4 py-2.5">
                <div class="flex justify-end gap-1">
                  <button
                    type="button" title="Guardar"
                    :disabled="guardandoCuota === v.villa"
                    class="rounded-lg p-1.5 text-brand-700 hover:bg-brand-50 disabled:opacity-50"
                    @click="guardarCuotaEspecial(v.villa)"
                  >
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="h-4 w-4"><path stroke-linecap="round" stroke-linejoin="round" :d="iconGuardar" /></svg>
                  </button>
                  <button
                    type="button" title="Desactivar"
                    :disabled="guardandoCuota === v.villa"
                    class="rounded-lg p-1.5 text-espresso-700/70 hover:bg-espresso-800/5 hover:text-espresso-800 disabled:opacity-50"
                    @click="desactivarCuotaEspecial(v.villa)"
                  >
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="h-4 w-4"><path stroke-linecap="round" stroke-linejoin="round" :d="iconOjoTachado" /></svg>
                  </button>
                  <button
                    type="button" title="Eliminar"
                    :disabled="guardandoCuota === v.villa"
                    class="rounded-lg p-1.5 text-wine-700 hover:bg-wine-50 disabled:opacity-50"
                    @click="eliminarCuotaEspecial(v.villa)"
                  >
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="h-4 w-4"><path stroke-linecap="round" stroke-linejoin="round" :d="iconEliminar" /></svg>
                  </button>
                </div>
              </td>
            </tr>
            <tr v-if="!cargandoCuotas && villasCuotaEspecial.length === 0">
              <td colspan="4" class="px-4 py-8 text-center text-espresso-800/40">Ninguna villa marcada con cuota especial</td>
            </tr>
          </tbody>
        </table>
      </div>
      </div>
    </section>

    <!-- Formas de pago -->
    <section v-if="tab === 'formas-pago'">
      <div class="mb-5 flex items-center justify-between">
        <p class="font-display text-lg font-semibold text-espresso-800">Formas de pago</p>
        <button
          class="rounded-lg bg-gradient-to-r from-wine-500 via-brand-500 to-gold-500 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:opacity-90"
          @click="mostrarFormFormaPago = !mostrarFormFormaPago"
        >
          {{ mostrarFormFormaPago ? 'Cancelar' : 'Nueva forma de pago' }}
        </button>
      </div>

      <form v-if="mostrarFormFormaPago" class="mb-6 flex gap-2 rounded-xl border border-gold-300/30 bg-cream-50 p-5 shadow-sm" @submit.prevent="crearFormaPago">
        <input v-model="nuevaFormaPago" required maxlength="40" placeholder="Ej. Depósito móvil" class="w-full rounded-lg border border-espresso-800/15 bg-white px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-200" />
        <button
          type="submit"
          :disabled="guardandoFormaPago"
          class="shrink-0 rounded-lg bg-gradient-to-r from-wine-500 via-brand-500 to-gold-500 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:opacity-90 disabled:opacity-50"
        >
          {{ guardandoFormaPago ? 'Creando...' : 'Crear' }}
        </button>
      </form>

      <div class="overflow-hidden rounded-xl border border-gold-300/30 bg-cream-50 shadow-sm">
        <table class="min-w-full divide-y divide-gold-300/20 text-sm">
          <thead class="bg-brand-50/60">
            <tr>
              <th class="px-4 py-2.5 text-left font-medium text-espresso-800/70">Nombre</th>
              <th class="px-4 py-2.5 text-left font-medium text-espresso-800/70">Estado</th>
              <th class="px-4 py-2.5"></th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gold-300/15">
            <tr v-for="f in formasPago" :key="f.id" class="hover:bg-brand-50/40">
              <template v-if="editandoFormaPago === f.id">
                <td class="px-4 py-2.5">
                  <input v-model="nombreEditFormaPago" required maxlength="40" class="w-full rounded-lg border border-espresso-800/15 bg-white px-2.5 py-1.5 text-sm focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-200" />
                </td>
                <td class="px-4 py-2.5"></td>
                <td class="px-4 py-2.5">
                  <div class="flex justify-end gap-1">
                    <button type="button" title="Guardar" :disabled="accionEnCursoFormaPago === f.id" class="rounded-lg p-1.5 text-brand-700 hover:bg-brand-50 disabled:opacity-40" @click="guardarEdicionFormaPago(f)">
                      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="h-4 w-4"><path stroke-linecap="round" stroke-linejoin="round" :d="iconGuardar" /></svg>
                    </button>
                    <button type="button" title="Cancelar" :disabled="accionEnCursoFormaPago === f.id" class="rounded-lg p-1.5 text-espresso-700/60 hover:bg-espresso-800/5 hover:text-espresso-700 disabled:opacity-40" @click="cancelarEdicionFormaPago">
                      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="h-4 w-4"><path stroke-linecap="round" stroke-linejoin="round" :d="iconCancelar" /></svg>
                    </button>
                  </div>
                </td>
              </template>
              <template v-else>
                <td class="px-4 py-2.5 font-medium text-espresso-900">{{ f.nombre }}</td>
                <td class="px-4 py-2.5">
                  <span class="rounded-full px-2.5 py-0.5 text-xs font-medium" :class="f.activo ? 'bg-brand-100 text-brand-800' : 'bg-espresso-800/10 text-espresso-800/50'">
                    {{ f.activo ? 'Activo' : 'Inactivo' }}
                  </span>
                </td>
                <td class="px-4 py-2.5">
                  <div class="flex justify-end gap-1">
                    <button type="button" title="Editar" :disabled="accionEnCursoFormaPago === f.id" class="rounded-lg p-1.5 text-brand-700 hover:bg-brand-50 disabled:opacity-40" @click="iniciarEdicionFormaPago(f)">
                      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="h-4 w-4"><path stroke-linecap="round" stroke-linejoin="round" :d="iconEditar" /></svg>
                    </button>
                    <button type="button" :title="f.activo ? 'Desactivar' : 'Activar'" :disabled="accionEnCursoFormaPago === f.id" class="rounded-lg p-1.5 text-espresso-700/70 hover:bg-espresso-800/5 hover:text-espresso-800 disabled:opacity-40" @click="alternarActivoFormaPago(f)">
                      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="h-4 w-4"><path stroke-linecap="round" stroke-linejoin="round" :d="f.activo ? iconOjoTachado : iconOjo" /></svg>
                    </button>
                    <button type="button" title="Eliminar" :disabled="accionEnCursoFormaPago === f.id" class="rounded-lg p-1.5 text-wine-700 hover:bg-wine-50 disabled:opacity-40" @click="eliminarFormaPago(f)">
                      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="h-4 w-4"><path stroke-linecap="round" stroke-linejoin="round" :d="iconEliminar" /></svg>
                    </button>
                  </div>
                </td>
              </template>
            </tr>
            <tr v-if="!cargandoFormasPago && formasPago.length === 0">
              <td colspan="3" class="px-4 py-8 text-center text-espresso-800/40">Sin formas de pago</td>
            </tr>
          </tbody>
        </table>
      </div>
    </section>

    <!-- Bitácora -->
    <section v-if="tab === 'bitacora'">
      <p class="mb-5 font-display text-lg font-semibold text-espresso-800">Bitácora de cambios</p>

      <form class="mb-6 flex flex-wrap items-end gap-3 rounded-xl border border-gold-300/30 bg-cream-50 p-4 shadow-sm" @submit.prevent="filtrarBitacora">
        <div>
          <label class="mb-1 block text-sm font-medium text-espresso-700">Tipo de cambio</label>
          <select v-model="filtroEntidad" class="rounded-lg border border-espresso-800/15 bg-white px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-200">
            <option value="">Todos</option>
            <option v-for="e in ENTIDADES" :key="e.value" :value="e.value">{{ e.label }}</option>
          </select>
        </div>
        <div>
          <label class="mb-1 block text-sm font-medium text-espresso-700">Acción</label>
          <select v-model="filtroAccion" class="rounded-lg border border-espresso-800/15 bg-white px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-200">
            <option value="">Todas</option>
            <option v-for="a in ACCIONES" :key="a.value" :value="a.value">{{ a.label }}</option>
          </select>
        </div>
        <div>
          <label class="mb-1 block text-sm font-medium text-espresso-700">Desde</label>
          <FechaInput v-model="filtroDesde" class="w-40 rounded-lg border border-espresso-800/15 bg-white px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-200" />
        </div>
        <div>
          <label class="mb-1 block text-sm font-medium text-espresso-700">Hasta</label>
          <FechaInput v-model="filtroHasta" :min="filtroDesde" class="w-40 rounded-lg border border-espresso-800/15 bg-white px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-200" />
        </div>
        <div class="min-w-[12rem] flex-1">
          <label class="mb-1 block text-sm font-medium text-espresso-700">Buscar</label>
          <input v-model="filtroTexto" type="text" placeholder="Ej. Cuota de mantenimiento" class="w-full rounded-lg border border-espresso-800/15 bg-white px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-200" />
        </div>
        <button type="submit" class="rounded-lg bg-gradient-to-r from-wine-500 via-brand-500 to-gold-500 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:opacity-90">
          Filtrar
        </button>
        <button type="button" class="rounded-lg border border-espresso-800/20 px-4 py-2 text-sm font-medium text-espresso-700 hover:bg-brand-50" @click="limpiarFiltrosBitacora">
          Limpiar
        </button>
        <ExportarBotones @exportar="exportarBitacora" />
      </form>

      <p v-if="cargandoBitacora" class="text-espresso-800/40">Cargando...</p>

      <div class="overflow-hidden rounded-xl border border-gold-300/30 bg-cream-50 shadow-sm">
        <table class="min-w-full divide-y divide-gold-300/20 text-sm">
          <thead class="bg-brand-50/60">
            <tr>
              <th class="px-4 py-2.5 text-left font-medium text-espresso-800/70">Fecha</th>
              <th class="px-4 py-2.5 text-left font-medium text-espresso-800/70">Usuario</th>
              <th class="px-4 py-2.5 text-left font-medium text-espresso-800/70">Cambio</th>
              <th class="px-4 py-2.5 text-left font-medium text-espresso-800/70">Descripción</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gold-300/15">
            <tr v-for="r in registros" :key="r.id" class="hover:bg-brand-50/40">
              <td class="whitespace-nowrap px-4 py-2.5 text-espresso-800/70">{{ formatearFechaHora(r.fecha) }}</td>
              <td class="px-4 py-2.5 font-medium text-espresso-900">{{ r.usuario }}</td>
              <td class="px-4 py-2.5">
                <span class="rounded-full px-2.5 py-0.5 text-xs font-medium" :class="colorAccion(r.accion)">
                  {{ ACCIONES.find((a) => a.value === r.accion)?.label ?? r.accion }} · {{ etiquetaEntidad(r.entidad) }}
                </span>
              </td>
              <td class="px-4 py-2.5 text-espresso-800/80">{{ r.descripcion }}</td>
            </tr>
            <tr v-if="!cargandoBitacora && registros.length === 0">
              <td colspan="4" class="px-4 py-8 text-center text-espresso-800/40">Sin registros para estos filtros</td>
            </tr>
          </tbody>
        </table>
      </div>
      <PaginacionControles
        :pagina="metaBitacora.pagina"
        :ultima-pagina="metaBitacora.ultima_pagina"
        :total="metaBitacora.total"
        :por-pagina="porPaginaBitacora"
        @update:pagina="irAPaginaBitacora"
        @update:por-pagina="cambiarPorPaginaBitacora"
      />
    </section>
  </div>
</template>
