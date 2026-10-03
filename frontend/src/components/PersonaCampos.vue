<script setup lang="ts">
import FechaInput from './FechaInput.vue'
import { soloLetras, soloTelefono, mayusculas, correo } from '../lib/filtrosCampo'

/**
 * Campos de una persona (propietario o encargado): nombres, apellidos, DNI/pasaporte, tres
 * telefonos, dos correos y fecha de nacimiento. Con `requerido`, nombres/apellidos son obligatorios
 * y se pide al menos un telefono y un correo (propietario); sin el, todo es opcional (encargado).
 *
 * Edita directamente el objeto reactivo que recibe.
 */
const props = defineProps<{
  persona: {
    NOMBRES: string
    APELLIDOS: string
    DNI: string
    TELF: string
    CELULAR: string
    OTRO_TEL: string
    MAIL: string
    MAIL2: string
    FECHA_NAC: string
    /** Solo el encargado: parentesco o vinculo con el propietario */
    PARENTESCO?: string
  }
  requerido?: boolean
  /** Muestra el campo "Parentesco / Vínculo" (solo encargado) */
  conParentesco?: boolean
  /** "del propietario" / "del encargado", para la etiqueta de fecha de nacimiento */
  de: string
}>()

const p = props.persona

const claseInput =
  'w-full rounded-lg border border-espresso-800/15 bg-white px-3 py-2.5 text-sm placeholder:text-espresso-800/30 focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-200 disabled:bg-cream-200 disabled:text-espresso-800/50'
const claseLabel = 'mb-1.5 block text-sm font-medium text-espresso-700'

function valor(e: Event): string {
  return (e.target as HTMLInputElement).value
}
</script>

<template>
  <div class="space-y-6">
    <div class="grid grid-cols-1 gap-x-5 gap-y-5 sm:grid-cols-2">
      <div>
        <label :class="claseLabel">Nombres<span v-if="requerido" class="text-wine-500"> *</span></label>
        <input :value="p.NOMBRES" maxlength="60" placeholder="ANA" title="Solo letras" :class="claseInput" @input="p.NOMBRES = soloLetras(valor($event))" />
      </div>
      <div>
        <label :class="claseLabel">Apellidos<span v-if="requerido" class="text-wine-500"> *</span></label>
        <input :value="p.APELLIDOS" maxlength="60" placeholder="SÁNCHEZ" title="Solo letras" :class="claseInput" @input="p.APELLIDOS = soloLetras(valor($event))" />
      </div>
      <div v-if="conParentesco" class="sm:col-span-2">
        <label :class="claseLabel">Parentesco / Vínculo</label>
        <input :value="p.PARENTESCO" maxlength="60" placeholder="HERMANO, ADMINISTRADOR, AMIGO..." :class="claseInput" @input="p.PARENTESCO = mayusculas(valor($event))" />
      </div>
      <div>
        <label :class="claseLabel">DNI / Pasaporte</label>
        <input :value="p.DNI" maxlength="30" placeholder="0801-1990-12345" :class="claseInput" @input="p.DNI = mayusculas(valor($event))" />
      </div>
      <div>
        <label :class="claseLabel">Fecha de nacimiento {{ de }}</label>
        <FechaInput v-model="p.FECHA_NAC" :class="claseInput" />
      </div>
    </div>

    <div>
      <p v-if="requerido" class="mb-1.5 text-xs font-medium text-espresso-800/50">Al menos uno de los tres es requerido</p>
      <div class="grid grid-cols-1 gap-x-5 gap-y-5 sm:grid-cols-3">
        <div>
          <label :class="claseLabel">Celular 1</label>
          <input :value="p.TELF" inputmode="numeric" maxlength="20" title="Solo números" placeholder="9897-2123" :class="claseInput" @input="p.TELF = soloTelefono(valor($event))" />
        </div>
        <div>
          <label :class="claseLabel">Celular 2</label>
          <input :value="p.CELULAR" inputmode="numeric" maxlength="20" title="Solo números" placeholder="9988-1201" :class="claseInput" @input="p.CELULAR = soloTelefono(valor($event))" />
        </div>
        <div>
          <label :class="claseLabel">Otro</label>
          <input :value="p.OTRO_TEL" inputmode="numeric" maxlength="20" title="Solo números" placeholder="2234-5601" :class="claseInput" @input="p.OTRO_TEL = soloTelefono(valor($event))" />
        </div>
      </div>
    </div>

    <div>
      <p v-if="requerido" class="mb-1.5 text-xs font-medium text-espresso-800/50">Al menos uno de los dos es requerido</p>
      <div class="grid grid-cols-1 gap-x-5 gap-y-5 sm:grid-cols-2">
        <div>
          <label :class="claseLabel">Correo Electrónico 1</label>
          <input :value="p.MAIL" type="email" maxlength="60" pattern="[^\s@]+@[^\s@]+\.[a-zA-Z]{2,}" title="Debe incluir un dominio, ej. nombre@dominio.com" placeholder="ana.sanchez@gmail.com" :class="claseInput" @input="p.MAIL = correo(valor($event))" />
        </div>
        <div>
          <label :class="claseLabel">Correo Electrónico 2</label>
          <input :value="p.MAIL2" type="email" maxlength="60" pattern="[^\s@]+@[^\s@]+\.[a-zA-Z]{2,}" title="Debe incluir un dominio, ej. nombre@dominio.com" placeholder="ana.sanchez@hotmail.com" :class="claseInput" @input="p.MAIL2 = correo(valor($event))" />
        </div>
      </div>
    </div>
  </div>
</template>
