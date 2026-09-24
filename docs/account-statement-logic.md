# TPVADMIN — Lógica actual del estado de cuenta y el saldo

## Pregunta central: ¿cómo obtiene hoy el sistema el saldo y el detalle de deuda de una villa?

Respuesta reconstruida de tres procedimientos almacenados: `SP_SALDOCLIE1` (saldo global),
`SP_DETALLECXC1` (detalle con saldo por factura) y `SP_FACTDETALLECXC1` (detalle de una factura
puntual). Los tres leen de `CUEN1` (movimientos) unido a `CONC1` (catálogo de conceptos, con la
columna `TIPO` = `'A'` o `'C'`).

### ⚠️ Hallazgo importante: contradicción en el significado de `CONC1.TIPO`

Transcribo los tres procedimientos tal cual están en el script, porque **no son consistentes entre
sí** sobre qué significa `TIPO='A'` y `TIPO='C'`:

**`SP_SALDOCLIE1`** (actualiza `CLIE1.SALDO`):
```sql
SELECT @ABONOS = COALESCE(SUM(IMPORTE),0) FROM CUEN1 JOIN CONC1 ... WHERE CONC1.TIPO = 'A'
SELECT @CARGOS = COALESCE(SUM(IMPORTE),0) FROM CUEN1 JOIN CONC1 ... WHERE CONC1.TIPO = 'C'
UPDATE CLIE1 SET SALDO = @CARGOS - @ABONOS
```
Aquí la variable `@ABONOS` se llena con los movimientos `TIPO='A'`, y `@CARGOS` con los `TIPO='C'`.

**`SP_DETALLECXC1`** y **`SP_FACTDETALLECXC1`** (detalle de movimientos con saldo por documento):
```sql
(SELECT SUM(IMPORTE) ... WHERE G.TIPO = 'C') AS ABONO   -- alias ABONO para TIPO='C'
  -
(SELECT SUM(IMPORTE) ... WHERE G.TIPO = 'A') AS CARGO   -- alias CARGO para TIPO='A'
```
Aquí es exactamente al revés: `TIPO='C'` se etiqueta como **abono** y `TIPO='A'` como **cargo**.

Es decir, un procedimiento usa `'A'` como si fuera "Abono" y otro lo usa como si fuera "Cargo"
(y lo mismo, invertido, con `'C'`). **Uno de los dos procedimientos tiene un bug de nomenclatura
heredado**, o ambos son consistentes en el cálculo neto pero uno de los dos nombres de variable está
mal puesto — no se puede determinar cuál con certeza sin ver datos reales de `CONC1.DESCR` para cada
`TIPO`.

**Esto no se puede resolver leyendo solo el esquema.** Se resuelve con una sola consulta contra la
BD real de solo lectura:

```sql
SELECT NUM_CPTO, DESCR, TIPO, REFER, MOD FROM CONC1 ORDER BY TIPO, NUM_CPTO;
```

Si, por ejemplo, aparece una fila `DESCR = 'CUOTA MANTENIMIENTO'` con `TIPO = 'A'`, eso confirmaría
que `'A'` = Cargo (y por tanto `SP_DETALLECXC1` tiene la nomenclatura correcta y `SP_SALDOCLIE1` la
tiene invertida en los nombres de variable, aunque hay que revisar si el resultado neto del `UPDATE`
sigue siendo correcto en signo). Dejo esto marcado explícitamente como **pendiente de confirmación
con datos reales** antes de portar esta lógica al sistema nuevo — no se debe adivinar.

### Lo que sí es consistente entre los tres procedimientos

- El saldo de una villa = **suma de un tipo de movimiento − suma del otro tipo**, siempre agrupado
  por cliente (`CCLIE` / `CLV_CLIE`).
- `SP_DETALLECXC1` calcula ese saldo **por documento** (`F.REFER = CUEN1.REFER`), no solo el total
  del cliente — es decir, el sistema actual sí es capaz de decir "esta factura específica tiene tanto
  de saldo pendiente", lo cual es exactamente la capacidad que pide conservar
  `CLAUDE_CODE_DB_CONTEXT.md` (desglose por mes/cuota, no una cifra acumulada ciega).
- `SP_FACTDETALLECXC1` además invierte el signo para mostrar: cuando el concepto es tipo `'A'`
  (el que sea que signifique), el importe se muestra multiplicado por `-1`. Esto sugiere que en la
  UI actual, cargos y abonos se muestran con signos opuestos en una misma columna de "importe", y el
  saldo corrido se lee de manera acumulativa — coherente con el formato de estado de cuenta pedido en
  el documento de requerimientos (columnas Fecha/Descripción/Cargos/Créditos/Saldo).

## Estructura exacta del reporte de estado de cuenta actual

Las tablas `TempEstadoCtaCxc1_1/5/6/10` (una copia por terminal, ver `database-tables.md`) tienen
esta forma:

```
CLV_CLIE | NOMBRE | TIPO_MOV | DESCR | NO_FACTURA | DOCTO | FECHA_APLI | FECHA_VENC | CARGOS | ABONOS | TOTAL
```

Esto coincide casi columna por columna con lo pedido en el documento de requerimientos:
`FECHA / DESCRIPCION / CARGOS / CREDITOS / SALDOS`. La columna `TOTAL` de esta tabla temporal es,
casi con certeza, el **saldo corrido** (running balance) que se muestra en la última fila como
"saldo a la fecha".

**Importante:** no existe en el script ningún `CREATE VIEW`/`CREATE PROCEDURE` que llene estas
tablas `Temp*`. El llenado ocurre desde la aplicación de escritorio actual (fuera de este script), lo
que significa que el algoritmo exacto de armado línea por línea + saldo corrido **no está disponible
para inspección** en este export — solo tenemos su tabla de salida (el "molde"), no la receta
completa. La receta más cercana que sí tenemos es `SP_DETALLECXC1`.

## Abonos parciales, pagos que cubren varias cuotas, saldos a favor

- **Abonos parciales**: no hay ninguna restricción de que `CUEN1.IMPORTE` de un abono deba ser igual
  al total de la factura referenciada — el modelo (una fila de movimiento por transacción, con saldo
  calculado por suma) soporta naturalmente abonos parciales: el saldo pendiente de una factura es
  simplemente cargos-menos-abonos para ese `REFER`, sin importar en cuántas partes se pagó.
- **Pagos que cubren varias cuotas**: como cada fila de `CUEN1` de tipo abono tiene un solo `REFER`,
  un pago que cubre varias facturas tendría que generar **varias filas** en `CUEN1` (una por factura
  cubierta), probablemente todas con el mismo `DOCTO` (el recibo) pero distinto `REFER`. Esto es
  coherente con `RECCXC1.PORCON` ("por concepto de", texto libre) — probablemente ahí se registraba
  antes en texto libre, y `CUEN1` en filas. **A confirmar con datos reales.**
- **Saldos a favor / anticipos**: `SP_ANTICIPOCXC1` existe específicamente para esto. Su lógica resta
  del total de créditos aplicados a una factura los créditos ya "consumidos" por otros cargos — el
  detalle exacto es intrincado y merece una revisión dedicada cuando haya acceso a datos reales, pero
  su sola existencia confirma que el sistema actual sí contempla el caso "el propietario pagó de más".
- **Cargos extraordinarios**: no hay una tabla o bandera separada para "cargo extraordinario" vs.
  "cuota regular" — la única diferenciación disponible es el concepto (`CONC1.DESCR`/`NUM_CPTO`)
  asociado al movimiento. El nuevo sistema probablemente necesite su propio catálogo de conceptos
  (cuota, cargo extraordinario, mora, etc.), inspirado en `CONC1` pero con esa distinción explícita.
- **Intereses/moras**: no se encontró ninguna tabla, columna o procedimiento relacionado con cálculo
  de mora/interés en todo el script. Si el sistema actual los aplica, se haría manualmente como un
  concepto de cargo más — no hay lógica automática de mora que migrar.

## Recomendación para el diseño nuevo (pendiente de aprobación del usuario)

No se ha diseñado nada todavía — esto es solo la lectura de lo existente. Cuando se autorice pasar a
diseño, el patrón `CUEN1` (un renglón por movimiento, con referencia a la factura que cubre) es un
buen punto de partida conceptual para el nuevo esquema, reemplazando la ambigüedad de `TIPO_MOV`/
`CONC1.TIPO` por un modelo explícito de "cargos" y "abonos" en tablas o un enum separados, y
agregando una dimensión explícita de período (mes/año) que hoy no existe.
