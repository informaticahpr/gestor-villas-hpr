<script setup lang="ts">
import { ref, watch, onMounted } from 'vue'
import { formatearFecha, enmascararFecha, parsearFecha } from '../lib/fechaFormato'

/**
 * Campo de fecha que siempre se escribe y se ve como DD/MM/AAAA, sin depender del idioma del
 * navegador (el <input type="date"> nativo muestra MM/DD/AAAA en un navegador en ingles).
 *
 * `v-model` maneja la fecha como AAAA-MM-DD (como la API) o '' si esta vacia o incompleta.
 * Las barras se ponen solas al escribir, y el boton de la derecha abre un calendario.
 * Los errores (fecha inexistente, fuera de `min`/`max`) usan la validacion nativa del formulario,
 * asi que impiden el envio igual que cualquier otro campo.
 */
defineOptions({ inheritAttrs: false })

const props = defineProps<{
  modelValue: string
  min?: string
  max?: string
  required?: boolean
  disabled?: boolean
}>()

const emit = defineEmits<{ 'update:modelValue': [valor: string] }>()

const texto = ref(formatearFecha(props.modelValue))
const entrada = ref<HTMLInputElement | null>(null)
const selector = ref<HTMLInputElement | null>(null)

// lo ultimo que emitio este componente; sirve para distinguir un cambio externo del v-model
let ultimoEmitido = props.modelValue

watch(
  () => props.modelValue,
  (nuevo) => {
    if (nuevo === ultimoEmitido) return
    ultimoEmitido = nuevo
    texto.value = formatearFecha(nuevo)
    validar()
  },
)
watch(() => [props.min, props.max], validar)

function mensajeDeError(): string {
  if (texto.value === '') return '' // si es obligatorio, lo avisa el navegador
  const iso = parsearFecha(texto.value)
  if (!iso) return 'Escribe una fecha válida con el formato DD/MM/AAAA.'
  if (props.min && iso < props.min) return `La fecha no puede ser anterior al ${formatearFecha(props.min)}.`
  if (props.max && iso > props.max) return `La fecha no puede ser posterior al ${formatearFecha(props.max)}.`
  return ''
}

function validar() {
  entrada.value?.setCustomValidity(mensajeDeError())
}

function emitir(valor: string) {
  ultimoEmitido = valor
  emit('update:modelValue', valor)
}

function alEscribir(evento: Event) {
  const campo = evento.target as HTMLInputElement
  texto.value = enmascararFecha(campo.value)
  campo.value = texto.value // fuerza lo enmascarado aunque Vue no detecte cambio
  emitir(parsearFecha(texto.value) ?? '')
  validar()
}

function abrirCalendario() {
  selector.value?.showPicker?.()
}

function alElegirEnCalendario(evento: Event) {
  const iso = (evento.target as HTMLInputElement).value
  texto.value = formatearFecha(iso)
  emitir(iso)
  validar()
}

onMounted(validar)
</script>

<template>
  <div class="relative">
    <input
      ref="entrada"
      v-bind="$attrs"
      type="text"
      inputmode="numeric"
      autocomplete="off"
      maxlength="10"
      placeholder="DD/MM/AAAA"
      style="padding-right: 2.25rem"
      :value="texto"
      :required="required"
      :disabled="disabled"
      @input="alEscribir"
      @blur="validar"
    />
    <button
      type="button"
      tabindex="-1"
      title="Elegir en el calendario"
      :disabled="disabled"
      class="absolute inset-y-0 right-0 flex items-center px-2.5 text-espresso-800/50 hover:text-brand-700 disabled:cursor-not-allowed disabled:opacity-40"
      @click="abrirCalendario"
    >
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="h-4 w-4">
        <path
          stroke-linecap="round"
          stroke-linejoin="round"
          d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 11.25v7.5"
        />
      </svg>
    </button>
    <!-- selector nativo, oculto: solo se usa para abrir el calendario del navegador -->
    <input
      ref="selector"
      type="date"
      tabindex="-1"
      aria-hidden="true"
      class="pointer-events-none absolute bottom-0 left-0 h-0 w-0 opacity-0"
      :value="modelValue"
      :min="min"
      :max="max"
      :disabled="disabled"
      @change="alElegirEnCalendario"
    />
  </div>
</template>
