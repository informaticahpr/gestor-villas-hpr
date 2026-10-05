# PASOS PARA MÓDULO DE MORA

Contexto completo del módulo de **mora mensual** que se desarrolló y probó en local (octubre 2026) y se dejó
**guardado, sin implementar**, en la rama de Git **`modulo-mora`**. La rama `main` (la que despliega Railway)
NO lo tiene.

---

## 1. Reglas acordadas con el usuario

1. **Cuándo:** la mora se aplica **automáticamente el día 5** de cada mes.
2. **A quién:** solo a villas con **"Aplicar cuota mensual"** activado que tengan **deuda anterior**.
3. **Sobre qué monto (base):** la **deuda anterior pendiente**, es decir, lo que la villa debe al día 5
   **sin contar los cargos de ese mes** (la cuota de mantenimiento del día 1 NO genera mora ese mismo mes).
   - Ejemplo del usuario: debe **$300** al 31/10; el 01/11 se carga la cuota de **$160** →
     mora de noviembre = **$300 × 0.83 % = $2.49** (no sobre $460).
   - Lo que pague entre el día 1 y el 5 reduce la deuda anterior (si abona $100 el día 3 → mora sobre $200 = $1.66).
   - La base incluye moras de meses anteriores que sigan vigentes (se acumula mes a mes).
   - Una villa que solo debe la cuota del mes en curso **no** paga mora ese mes.
4. **Porcentaje:** **0.83 %** por defecto, **configurable** en *Configuración → Cuotas → Mora mensual*
   (Director/Admin).
5. **Una sola mora por villa y por mes.** Si el proceso corre varias veces, no duplica.
6. **Anulación automática:** si **antes de que termine el mes** el propietario paga, la mora de ese mes
   **se anula sola** (anulada por "Sistema").
   - Implementado hoy: se anula cuando paga **todo** lo que debe (saldo sin contar esa mora ≤ 0),
     incluida la cuota del mes.
   - ❓ **PENDIENTE DE CONFIRMAR con el usuario:** con la base nueva (deuda anterior), ¿basta con que pague
     la **deuda anterior** sobre la que se calculó la mora (los $300 del ejemplo), aunque deba la cuota del mes?
7. **Arreglo de pago:** si el propietario paga todo lo atrasado (ej. debe $1,920 de todo el año y en
   diciembre paga todo de una vez), **el Director** puede anular las moras acumuladas de la villa (todas
   o las que elija), con un **motivo obligatorio**. Queda en la bitácora.
   - El **Admin** también tiene el permiso, **solo para hacer pruebas** (decisión del usuario). El texto en
     pantalla dice que lo hace el Director.
   - El Supervisor no puede.
8. **Lo que se ve:**
   - Las moras salen en el **estado de cuenta** como cargo "Mora", con folio y descripción del cálculo.
   - Las moras **anuladas no salen** en el estado de cuenta (usa la anulación de movimientos que ya existe).
   - En **Reportes → Por Concepto → Mora** se listan las moras vigentes de un rango.

---

### ⚠️ Aclaración del usuario (posterior a lo programado) — CAMBIA EL MOMENTO DE LA CARGA

> "Empieza a calcularse, pero no se ve reflejado. Eso se carga el **01/MM siguiente a las 00:01** y ya en el
> día, a las **08:00 AM**, se cargarán las **cuotas de mantenimiento**."

Interpretación para implementar (confirmar al retomar):

- La mora del mes **M** se **calcula** desde el día 5 de M (deuda anterior que no pagó a tiempo), pero
  **no se carga ni se ve** durante el mes.
- Se **carga el día 1 del mes siguiente (M+1) a las 00:01**, antes de la cuota.
- A las **08:00 AM** del mismo día 1 se cargan las **cuotas de mantenimiento** (¿también automáticas? hoy
  se aplican a mano desde Cargo/Crédito → "aplicar a todas"; confirmar si deben pasar a ser automáticas).
- Con esto, la "anulación automática" se vuelve más simple: si **antes de fin de mes** pagó la deuda sobre la
  que se calculaba la mora, **simplemente no se carga** el día 1 (no hace falta anular nada).
- El arreglo de pago (Director) sigue igual: anula moras **ya cargadas** de meses anteriores.

Cambios a hacer sobre lo programado:
1. `routes/console.php`: programar `moras:aplicar` el **día 1 a las 00:01** (no diario a las 00:10) y que
   calcule la mora del **mes anterior**.
2. `MoraService::aplicarDelMes()`: la base = deuda anterior pendiente **al cierre del mes anterior** que
   ya existía al día 5 de ese mes (definir con el usuario el caso de pagos parciales entre el 5 y fin de mes).
   `FECHA_APLI` = día 1 del mes nuevo.
3. Quitar `anularSiPagoAntesDeFinDeMes()` del registro de abonos (ya no hace falta) o dejarlo solo para
   moras ya cargadas, según lo que confirme el usuario.
4. Si las cuotas pasan a ser automáticas: comando programado el día 1 a las 08:00 que haga lo mismo que
   "aplicar a todas" con la cuota de mantenimiento (respetando cuota especial y "Aplicar cuota mensual").

---

## 2. Cómo está hecho (código en la rama `modulo-mora`)

### Base de datos — migración `2026_10_06_100000_mora_mensual.php`
- `conceptos.ES_MORA` (boolean): marca el concepto de mora. La migración marca el concepto **"Mora"** que
  ya existe (seeder) o lo crea si no existe.
- `movimientos.MORA_PERIODO` (string "AAAA-MM", con índice): identifica la mora automática de cada villa y
  mes. Sirve para no duplicar y para saber cuál anular al pagar.
- Tabla nueva `parametros` (`clave`, `valor`): guarda `mora_porcentaje` = `0.83`.

### Backend
| Archivo | Qué hace |
|---|---|
| `app/Services/MoraService.php` | Lógica: `aplicarDelMes()`, `anularSiPagoAntesDeFinDeMes()`, `morasVigentes()`, `anularPorArreglo()`, `cargosDelMes()` |
| `app/Console/Commands/AplicarMoras.php` | Comando `php artisan moras:aplicar [--fecha=AAAA-MM-DD]` (registra en bitácora) |
| `routes/console.php` | Programa `moras:aplicar` **todos los días a las 00:10** (antes del día 5 no hace nada; si el servidor estuvo apagado el 5, la aplica al volver) |
| `app/Http/Controllers/Api/MoraController.php` | `GET/PUT /api/mora/configuracion` (porcentaje), `GET /api/villas/{villa}/moras`, `POST /api/villas/{villa}/arreglo-pago` — todo Director/Admin |
| `app/Http/Controllers/Api/MovimientoController.php` | Al registrar un **abono**, llama a `anularSiPagoAntesDeFinDeMes()` y responde `mora_anulada` |
| `app/Models/Parametro.php` | Modelo clave/valor + `Parametro::porcentajeMora()` |
| `app/Models/Concepto.php` | `Concepto::mora()` + cast `ES_MORA` |
| `app/Models/Movimiento.php` | Campo `MORA_PERIODO`; `anular(?User ...)` acepta `null` = anulado por el Sistema |
| `app/Support/Formato.php` | `Formato::numero()` (0.83 → "0.83") |
| `app/Http/Controllers/Api/BitacoraController.php` | Entidad nueva `mora` → "Mora" |
| `resources/views/recibos/_cuerpo.blade.php` | "Anulado por **Sistema**" cuando no hay usuario |
| `Dockerfile` | `CMD` arranca `php artisan schedule:work &` en segundo plano (para que Railway aplique la mora el día 5) |

- Cada mora lleva **folio de cargo** (CA…), `FECHA_APLI` = día 5, `FECHA_VENC` = día 5 y descripción:
  *"Mora de noviembre 2026: 0.83% sobre $300.00 de saldo anterior pendiente"*.

### Frontend
| Archivo | Qué hace |
|---|---|
| `src/components/ArregloPagoModal.vue` | Ventana del arreglo de pago: lista de moras vigentes con casillas (todas marcadas), saldo actual / moras a anular / saldo quedaría, motivo obligatorio |
| `src/components/VillaModal.vue` | Botón **"Arreglo de pago"** en la pestaña *Reporte* de la villa (Director/Admin) |
| `src/components/MovimientoModal.vue` | Aviso cuando un abono anula sola la mora del mes |
| `src/views/ConfiguracionView.vue` | Tarjeta **"Mora mensual"** en Configuración → Cuotas (porcentaje + ejemplo con $1,000) y entidad "Mora" en la bitácora |
| `src/types.ts` | Entidad de bitácora `mora` |

---

## 3. Pruebas que se hicieron (todas pasaron, en local)

- Antes del día 5 no aplica nada; el día 5 aplica; correrlo otra vez **no duplica**.
- Ejemplo del usuario: debe $300, cuota $160 el 01/11 → mora **$2.49**; si abona $100 el día 3 → **$1.66**;
  villa al día (solo debe la cuota del mes) → **sin mora**.
- Abono parcial → la mora se queda; abono por todo lo adeudado → la mora **se anula sola** y deja de verse en
  el estado de cuenta.
- Arreglo de pago: se anularon 4 moras (julio a octubre) de una villa con motivo; quedó en bitácora.
- Con los datos importados del Excel, la mora de octubre 2026 con la regla final dio **35 villas, $888.59**.

---

## 4. Pendientes antes de implementarlo

1. Ajustar el **momento de la carga** según la aclaración del usuario (mora el día 1 a las 00:01 del mes
   siguiente, cuotas a las 08:00) y con eso cerrar la regla de anulación automática (ver sección ⚠️).
2. Decidir si el **Admin** conserva el permiso de arreglo de pago en producción o queda **solo el Director**
   (hoy lo tienen los dos; el Admin era para pruebas).
3. Revisar en el navegador (las pantallas compilan, pero no se probaron visualmente):
   la tarjeta de Configuración, el botón y la ventana de Arreglo de pago y el aviso al registrar un abono.
4. En Railway: confirmar que el `schedule:work` del Dockerfile corre (en los *Deploy Logs* debe verse el
   programador) y que el día 5 se aplica la mora.

---

## 5. Cómo retomarlo

```bash
# traer la rama y verla en local
git fetch
git checkout modulo-mora

# traer a la rama lo nuevo de main (si main avanzó mientras tanto)
git merge main

# base local: aplicar la migración de la mora
cd backend
php artisan migrate

# simular la mora de un mes en local (en local no corre el programador de tareas)
php artisan moras:aplicar --fecha=2026-11-05
```

Cuando esté aprobado:

```bash
git checkout main
git merge modulo-mora
git push
```

Al desplegar, Railway corre la migración (crea `ES_MORA`, `MORA_PERIODO` y `parametros`) y, por el
`Dockerfile`, el programador de tareas que aplica la mora cada día 5.

> Nota: en la base local, antes de guardar el módulo se borraron las moras de prueba de octubre y se
> deshizo la migración de la mora, para dejarla como `main`. Respaldos en `%TEMP%`:
> `database.antes-mora.sqlite`, `database.antes-mora-octubre.sqlite`, `database.antes-recalcular-mora.sqlite`.
