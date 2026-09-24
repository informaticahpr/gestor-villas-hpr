# TPVADMIN — Representación de la deuda histórica (pre-2020 y en general)

## Cómo se representa hoy una deuda de hace varios años

No existe ningún mecanismo especial de "deuda histórica" o "saldo inicial" como concepto propio en el
esquema: no hay tabla `SALDO_INICIAL`, `DEUDA_ANTERIOR`, ni columna equivalente en `CLIE1` o `CUEN1`.

Según la lógica reconstruida en `account-statement-logic.md`, una deuda de 2020 se representaría
simplemente como **filas normales en `CUEN1`** con `FECHA_APLI` en 2020, exactamente con la misma
estructura que un cargo de este mes. No hay diferencia estructural entre una cuota de enero 2020 y
una de este mes — ambas son una fila de `CUEN1` con un `TIPO_MOV` de cargo y una fecha.

Esto es, en principio, una buena noticia para el requisito central del proyecto ("no tratar una deuda
acumulada como una cifra sin desglose"): **si los datos existen como filas individuales de `CUEN1`
desde 2020**, el desglose mes a mes que pide el documento de contexto ya está implícito en los datos,
y "solo" hay que leerlo correctamente (agrupando por `REFER`/`NO_FACTURA` y ordenando por
`FECHA_APLI`), no reconstruirlo desde una cifra agregada.

## Lo que no se puede confirmar sin datos reales

- **Si esa granularidad realmente existe desde 2020 o antes**, o si en algún momento el sistema (o
  alguien manualmente) consolidó deuda vieja en un solo cargo "Saldo anterior" / "Saldo inicial" con
  una fecha ficticia. Esto es muy común en migraciones de sistemas anteriores a este — hay que
  revisarlo activamente.
- **Si `FACT1`/`PFACT1` tienen historial completo desde 2020** o si son más recientes que `CUEN1`
  (es decir, si en algún momento se empezó a usar `CUEN1` sin siempre generar la `FACT1` de
  respaldo, o viceversa).
- Si hay algún corte de sistema/migración anterior visible en los datos (ej. un salto de folios, un
  concepto especial tipo "SALDO INICIAL MIGRACION" en `CONC1`).

## Consultas de verificación recomendadas (solo lectura, contra la BD real)

Cuando haya acceso al servidor SQL Server real con el usuario `dev` (`db_datareader`), antes de diseñar
nada del sistema nuevo:

```sql
-- ¿Desde cuándo hay movimientos, y qué tan atomizados están?
SELECT MIN(FECHA_APLI) AS mas_antiguo, MAX(FECHA_APLI) AS mas_reciente, COUNT(*) AS total_movs
FROM CUEN1;

-- ¿Existen conceptos tipo "saldo inicial" o "migración"?
SELECT * FROM CONC1 WHERE DESCR LIKE '%SALDO%' OR DESCR LIKE '%INICIAL%' OR DESCR LIKE '%MIGRA%' OR DESCR LIKE '%ANTERIOR%';

-- Tomar una villa con antigüedad conocida y ver su historial completo, cronológico
SELECT CCLIE, TIPO_MOV, NO_FACTURA, DOCTO, REFER, IMPORTE, FECHA_APLI, FECHA_VENC
FROM CUEN1
WHERE CCLIE = '<villa con deuda vieja conocida>'
ORDER BY FECHA_APLI;

-- ¿El total de FACT1 (cargos) por villa cuadra con el total de CUEN1 tipo cargo?
SELECT CVE_CLIE, SUM(TOTAL) FROM FACT1 GROUP BY CVE_CLIE;
```

Estas consultas permiten responder con evidencia, antes de construir nada, si el desglose mensual
real está disponible tal cual en la base o si hay tramos de deuda "aplanada" que requerirán una
decisión de producto (¿se reconstruye estimando meses, o se conserva como un solo cargo histórico
"Saldo anterior al [fecha]" dentro del nuevo sistema?). **Esta decisión no se ha tomado — depende de
lo que muestren los datos reales.**

## Deuda anterior a 2020 específicamente

El documento de contexto menciona explícitamente que hay deuda que "se remonta aproximadamente a
2020 o antes". Nada en el esquema sugiere una fecha de corte especial en 2020 — es una fecha de
negocio (posiblemente cuándo se empezó a usar activamente este sistema o cuándo se instauró la cuota
actual), no algo visible estructuralmente. Confirmar con el cliente/usuario del negocio (no con la
BD) por qué 2020 es la fecha de referencia, y si antes de esa fecha existía otro sistema cuyos saldos
fueron migrados a `TPVADMIN` de alguna forma consolidada.
